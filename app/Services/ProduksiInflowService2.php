<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailTurusanKayu;
use App\Models\HargaPegawai;
use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class ProduksiInflowService
{
    public function getLaporanBatch()
    {
        // SOLUSI 1 & 3: Ambil hanya baris penutup batch (jumlah_batang > 0) dengan Pagination
        // Batasi kolom yang diambil untuk menghemat memori
        $paginatedClosures = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu'
        ])
            ->where('jumlah_batang', '>', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Menghasilkan link pagination otomatis

        $laporanFinal = [];

        foreach ($paginatedClosures as $closure) {
            // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang
            // Cari baris yang id_lahan & id_jenis_kayu sama, dan waktu <= penutup saat ini
            // namun > penutup sebelumnya (atau ambil semua yang belum punya penutup lain)

            $batchRecords = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc')
                ->get();

            // Kita potong (slice) hanya sampai penutup sebelumnya jika ada
            $tempGroup = [];
            foreach ($batchRecords as $record) {
                $tempGroup[] = $record;
                // Jika ketemu baris lain yang punya jumlah_batang > 0 (tapi bukan baris closure itu sendiri)
                if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                    array_pop($tempGroup); // Buang baris penutup batch lama itu
                    break;
                }
            }

            // Urutkan balik ke ASC untuk proses jahitan
            $tempGroup = array_reverse($tempGroup);
            $batch = $this->stitchBatchWithOutflow($tempGroup);

            // Tentukan Inflow Window
            // Cari penutup terakhir sebelum batch ini untuk lahan yang sama
            $lastClosure = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('created_at', '<', $batch['tgl_buka_raw'])
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            $start = $lastClosure ? $lastClosure->created_at : null;
            $end = $batch['tgl_buka_raw'];

            $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status']);

            $tglInflowPertama = $dataMasuk->min('tanggal');
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;
            $total_poin = number_format($dataMasuk->sum('poin'), 0, ',', '.');
            $harga_v_ongkos = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj']) / $batch['grand_total_outflow_m3'] ?? 1);
            $harga_v_ongkos_penyusutan = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj'] + $batch['grand_total_outflow_penyusutan']) / $batch['grand_total_outflow_m3'] ?? 1);

            $outflowCollection = collect($batch['outflow_detail']);
            $jenis_kayu = $outflowCollection->contains(function ($item) {
                $namaMesin = strtoupper($item['mesin'] ?? '');
                return str_contains($namaMesin, 'SPINDLESS') || str_contains($namaMesin, 'MERANTI');
            });

            $laporanFinal[] = [
                'batch_info' => $batchInfo,
                'inflow' => $dataMasuk,
                'outflow' => $batch['outflow_detail'],
                'summary' => [
                    'jenis_kayu' => $jenis_kayu ? "KAYU 260" : "KAYU 130",
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => $dataMasuk->sum('kubikasi'),
                    'total_keluar_m3' => (float) number_format($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => $total_poin,
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? number_format(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                        : '0%',
                    'harga_veneer' => (float) ($dataMasuk->sum('poin') / $batch['grand_total_outflow_m3'] ?? 1),
                    'harga_v_ongkos' => $harga_v_ongkos,
                    'harga_vop' => $harga_v_ongkos_penyusutan
                ]
            ];
        }

        // Kembalikan objek paginator agar view bisa merender links()
        return new LengthAwarePaginator(
            $laporanFinal,
            $paginatedClosures->total(),
            $paginatedClosures->perPage(),
            $paginatedClosures->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getLaporanBatchPreview($bulan = null, $tahun = null)
    {
        $bulan = $bulan ?: date('m');
        $tahun = $tahun ?: date('y');

        $paginatedClosures = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu'
        ])
            ->where('jumlah_batang', '>', 0)
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->orderBy('created_at', 'asc')
            ->get();

        $laporanFinal = [];

        foreach ($paginatedClosures as $closure) {
            // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang
            // Cari baris yang id_lahan & id_jenis_kayu sama, dan waktu <= penutup saat ini
            // namun > penutup sebelumnya (atau ambil semua yang belum punya penutup lain)

            $batchRecords = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc')
                ->get();

            // Kita potong (slice) hanya sampai penutup sebelumnya jika ada
            $tempGroup = [];
            foreach ($batchRecords as $record) {
                $tempGroup[] = $record;
                // Jika ketemu baris lain yang punya jumlah_batang > 0 (tapi bukan baris closure itu sendiri)
                if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                    array_pop($tempGroup); // Buang baris penutup batch lama itu
                    break;
                }
            }

            // Urutkan balik ke ASC untuk proses jahitan
            $tempGroup = array_reverse($tempGroup);
            $batch = $this->stitchBatchWithOutflow($tempGroup);

            // Tentukan Inflow Window
            // Cari penutup terakhir sebelum batch ini untuk lahan yang sama
            $lastClosure = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('created_at', '<', $batch['tgl_buka_raw'])
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            $start = $lastClosure ? $lastClosure->created_at : null;
            $end = $batch['tgl_buka_raw'];

            $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status']);

            $tglInflowPertama = $dataMasuk->min('tanggal');
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;
            $total_poin = number_format($dataMasuk->sum('poin'), 0, ',', '.');
            $harga_v_ongkos = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj']) / $batch['grand_total_outflow_m3'] ?? 1);
            $harga_v_ongkos_penyusutan = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj'] + $batch['grand_total_outflow_penyusutan']) / $batch['grand_total_outflow_m3'] ?? 1);

            $outflowCollection = collect($batch['outflow_detail']);
            $jenis_kayu = $outflowCollection->contains(function ($item) {
                $namaMesin = strtoupper($item['mesin'] ?? '');
                return str_contains($namaMesin, 'SPINDLESS') || str_contains($namaMesin, 'MERANTI');
            });

            $laporanFinal[] = [
                'batch_info' => $batchInfo,
                'inflow' => $dataMasuk,
                'outflow' => $batch['outflow_detail'],
                'summary' => [
                    'jenis_kayu' => $jenis_kayu ? "KAYU 260" : "KAYU 130",
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => $dataMasuk->sum('kubikasi'),
                    'total_keluar_m3' => (float) number_format($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => $total_poin,
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? number_format(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                        : '0%',
                    'harga_veneer' => (float) ($dataMasuk->sum('poin') / $batch['grand_total_outflow_m3'] ?? 1),
                    'harga_v_ongkos' => $harga_v_ongkos,
                    'harga_vop' => $harga_v_ongkos_penyusutan
                ]
            ];
        }

        // Kembalikan objek paginator agar view bisa merender links()
        return collect($laporanFinal);
    }

    public function getLaporanBatchRekapFix($bulan = null, $tahun = null, $lahan = null)
    {
        $bulan = $bulan ?: date('m');
        $tahun = $tahun ?: date('Y');

        $lahanX = $this->getActiveLahanSheets($bulan, $tahun)[0] ?? null;

        if ($lahan == null && $lahanX == null) {
            return null;
        }




        if (!isset($lahan)) {
            $lahan = $lahanX;
        }

        $ongkosPekerja = DB::table('harga_pegawais')->value('harga') ?? 0;

        $sql = "
            WITH REKAP_INFLOW AS (
                SELECT 
                    SUM(dtk.kuantitas) as total_batang_masuk,
                    SUM(ROUND((CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) * CAST(dtk.kuantitas AS DECIMAL(20,4)), 4)) as total_m3_masuk,
                    SUM(FLOOR((COALESCE(hk.harga_beli, 0) * ROUND((CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) * CAST(dtk.kuantitas AS DECIMAL(20,4)), 4)) * 1000)) as total_poin_inflow
                FROM detail_turusan_kayus dtk
                JOIN nota_kayus nk ON dtk.id_kayu_masuk = nk.id_kayu_masuk
                JOIN lahans l ON dtk.lahan_id = l.id
                LEFT JOIN harga_kayus hk ON dtk.jenis_kayu_id = hk.id_jenis_kayu 
                    AND dtk.grade = hk.grade AND dtk.panjang = hk.panjang 
                    AND dtk.diameter >= hk.diameter_terkecil AND dtk.diameter <= hk.diameter_terbesar
                WHERE nk.status LIKE '%Sudah Diperiksa%'
                AND l.nama_lahan = :lahan1
                AND MONTH(nk.created_at) = :bulan1 AND YEAR(nk.created_at) = :tahun1
            ),
            OUTFLOW_RAW AS (
                SELECT 
                    p.id as id_produksi,
                    p.tgl_produksi,
                    p.id_mesin,
                    m.penyusutan as nilai_penyusutan,
                    (SELECT COUNT(*) FROM pegawai_rotaries dpr WHERE dpr.id_produksi = p.id) as jumlah_pekerja,
                    (CAST(spu.panjang AS DECIMAL(20,4)) * CAST(spu.lebar AS DECIMAL(20,4)) * CAST(spu.tebal AS DECIMAL(20,4)) * CAST(dhp.total_lembar AS DECIMAL(20,4))) / 10000000 as m3_baris,
                    SUM((CAST(spu.panjang AS DECIMAL(20,4)) * CAST(spu.lebar AS DECIMAL(20,4)) * CAST(spu.tebal AS DECIMAL(20,4)) * CAST(dhp.total_lembar AS DECIMAL(20,4))) / 10000000) 
                        OVER(PARTITION BY p.id) as m3_total_harian
                FROM detail_hasil_palet_rotaries dhp
                JOIN produksi_rotaries p ON dhp.id_produksi = p.id
                JOIN penggunaan_lahan_rotaries plr ON plr.id_produksi = p.id
                JOIN lahans l ON plr.id_lahan = l.id
                JOIN mesins m ON p.id_mesin = m.id
                JOIN ukurans spu ON dhp.id_ukuran = spu.id
                WHERE l.nama_lahan = :lahan2
                AND MONTH(p.tgl_produksi) = :bulan2 AND YEAR(p.tgl_produksi) = :tahun2
            ),
            REKAP_OUTFLOW AS (
                SELECT 
                    SUM(m3_baris) as total_m3_keluar,
                    SUM(
                        CASE 
                            WHEN m3_total_harian = 0 THEN 0 
                            ELSE GREATEST(ROUND(jumlah_pekerja * (m3_baris / m3_total_harian)), 1) 
                        END * :ongkos_pekerja
                    ) as total_ongkos_final,
                    SUM(DISTINCT CASE WHEN id_produksi IS NOT NULL THEN nilai_penyusutan ELSE 0 END) as total_penyusutan_final
                FROM OUTFLOW_RAW
            )
            SELECT 
                i.*, o.*,
                COALESCE((o.total_m3_keluar / NULLIF(i.total_m3_masuk, 0)) * 100, 0) as total_rendemen,
                COALESCE((i.total_poin_inflow / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v,
                COALESCE(((i.total_poin_inflow + o.total_ongkos_final) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v_ongkos,
                COALESCE(((i.total_poin_inflow + o.total_ongkos_final + o.total_penyusutan_final) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_vop
            FROM REKAP_INFLOW i, REKAP_OUTFLOW o
        ";

        $result = DB::select($sql, [
            'lahan1' => $lahan,
            'bulan1' => $bulan,
            'tahun1' => $tahun,
            'lahan2' => $lahan,
            'bulan2' => $bulan,
            'tahun2' => $tahun,
            'ongkos_pekerja' => $ongkosPekerja
        ]);

        return $result[0] ?? null;
        /*
        
        $sql = "
            WITH REKAP_INFLOW AS (
                SELECT 
                    SUM(dtk.kuantitas) as total_batang_masuk,
                    SUM(ROUND((CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) * CAST(dtk.kuantitas AS DECIMAL(20,4)), 4)) as total_m3_masuk,
                    SUM(FLOOR((COALESCE(hk.harga_beli, 0) * ROUND((CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) * CAST(dtk.kuantitas AS DECIMAL(20,4)), 4)) * 1000)) as total_poin_inflow
                FROM detail_turusan_kayus dtk
                JOIN nota_kayus nk ON dtk.id_kayu_masuk = nk.id_kayu_masuk
                LEFT JOIN harga_kayus hk ON dtk.jenis_kayu_id = hk.id_jenis_kayu 
                    AND dtk.grade = hk.grade AND dtk.panjang = hk.panjang 
                    AND dtk.diameter >= hk.diameter_terkecil AND dtk.diameter <= hk.diameter_terbesar
                WHERE nk.status LIKE '%Sudah Diperiksa%'
                AND MONTH(nk.created_at) = :bulan1 AND YEAR(nk.created_at) = :tahun1
            ),
            OUTFLOW_RAW AS (
                -- Mengambil data dasar produksi untuk menghitung MSA dan Penyusutan
                SELECT 
                    p.id as id_produksi,
                    p.tgl_produksi,
                    p.id_mesin,
                    m.penyusutan as nilai_penyusutan,
                    (SELECT COUNT(*) FROM pegawai_rotaries dpr WHERE dpr.id_produksi = p.id) as jumlah_pekerja,
                    (CAST(spu.panjang AS DECIMAL(20,4)) * CAST(spu.lebar AS DECIMAL(20,4)) * CAST(spu.tebal AS DECIMAL(20,4)) * CAST(dhp.total_lembar AS DECIMAL(20,4))) / 10000000 as m3_baris,
                    -- Menghitung total output harian per ID Produksi untuk pembagi MSA
                    SUM((CAST(spu.panjang AS DECIMAL(20,4)) * CAST(spu.lebar AS DECIMAL(20,4)) * CAST(spu.tebal AS DECIMAL(20,4)) * CAST(dhp.total_lembar AS DECIMAL(20,4))) / 10000000) 
                        OVER(PARTITION BY p.id) as m3_total_harian
                FROM detail_hasil_palet_rotaries dhp
                JOIN produksi_rotaries p ON dhp.id_produksi = p.id
                JOIN mesins m ON p.id_mesin = m.id
                JOIN ukurans spu ON dhp.id_ukuran = spu.id
                WHERE MONTH(p.tgl_produksi) = :bulan2 AND YEAR(p.tgl_produksi) = :tahun2
            ),
            REKAP_OUTFLOW AS (
                -- Agregasi final Outflow dengan logika MSA dan Penyusutan Flat
                SELECT 
                    SUM(m3_baris) as total_m3_keluar,
                    -- Logika Ongkos: SUM( ROUND(Pekerja * (m3_baris / m3_total_harian)) * Ongkos )
                    SUM(
                        CASE 
                            WHEN m3_total_harian = 0 THEN 0 
                            ELSE GREATEST(ROUND(jumlah_pekerja * (m3_baris / m3_total_harian)), 1) 
                        END * :ongkos_pekerja
                    ) as total_ongkos_final,
                    -- Logika Penyusutan: Hanya ambil nilai penyusutan sekali per ID Produksi
                    SUM(DISTINCT CASE WHEN id_produksi IS NOT NULL THEN nilai_penyusutan ELSE 0 END) as total_penyusutan_final
                FROM OUTFLOW_RAW
            )
            SELECT 
                i.*, o.*,
                COALESCE((o.total_m3_keluar / NULLIF(i.total_m3_masuk, 0)) * 100, 0) as total_rendemen,
                COALESCE((i.total_poin_inflow / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v,
                COALESCE(((i.total_poin_inflow + o.total_ongkos_final) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v_ongkos,
                COALESCE(((i.total_poin_inflow + o.total_ongkos_final + o.total_penyusutan_final) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_vop
            FROM REKAP_INFLOW i, REKAP_OUTFLOW o
        ";

        $result = DB::select($sql, [
            'bulan1' => $bulan,
            'tahun1' => $tahun,
            'bulan2' => $bulan,
            'tahun2' => $tahun,
            'ongkos_pekerja' => $ongkosPekerja
        ]);

        return $result[0] ?? null;
            */
    }
public function getLaporanBatchRekap($bulan = null, $tahun = null)
    {
        $bulan = $bulan ?: date('m');
        $tahun = $tahun ?: date('Y');

        $sql = "
            WITH REKAP_INFLOW AS (
                SELECT 
                    SUM(dtk.kuantitas) as total_batang_masuk,
                    SUM(ROUND((CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) * CAST(dtk.kuantitas AS DECIMAL(20,4)), 4)) as total_m3_masuk,
                    SUM(
                        FLOOR(
                            (COALESCE(hk.harga_beli, 0) * ROUND(
                                (CAST(dtk.panjang AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * CAST(dtk.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) 
                                * CAST(dtk.kuantitas AS DECIMAL(20,4)), 
                            4)
                            ) * 1000
                        )
                    ) as total_poin_inflow
                FROM detail_turusan_kayus dtk
                JOIN nota_kayus nk ON dtk.id_kayu_masuk = nk.id_kayu_masuk
                LEFT JOIN harga_kayus hk ON dtk.jenis_kayu_id = hk.id_jenis_kayu 
                    AND dtk.grade = hk.grade 
                    AND dtk.panjang = hk.panjang 
                    AND dtk.diameter >= hk.diameter_terkecil 
                    AND dtk.diameter <= hk.diameter_terbesar
                WHERE nk.status LIKE '%Sudah Diperiksa%'
                AND MONTH(nk.created_at) = :bulan1 AND YEAR(nk.created_at) = :tahun1
            ),
            REKAP_OUTFLOW AS (
                SELECT 
                    SUM((CAST(spu.panjang AS DECIMAL(20,4)) * CAST(spu.lebar AS DECIMAL(20,4)) * CAST(spu.tebal AS DECIMAL(20,4)) * CAST(dhp.total_lembar AS DECIMAL(20,4))) / 10000000) as total_m3_keluar,
                    /* Agregasi Ongkos dan Penyusutan (Sesuaikan nama kolom jika berbeda di DB Anda) */
                    SUM(COALESCE(dhp.total_ongkos, 0)) as total_ongkos_pkj,
                    SUM(COALESCE(dhp.total_penyusutan, 0)) as total_penyusutan
                FROM detail_hasil_palet_rotaries dhp
                JOIN ukurans spu ON dhp.id_ukuran = spu.id
                WHERE MONTH(dhp.created_at) = :bulan2 AND YEAR(dhp.created_at) = :tahun2
            )
            SELECT 
                i.*, 
                o.*,
                COALESCE((o.total_m3_keluar / NULLIF(i.total_m3_masuk, 0)) * 100, 0) as total_rendemen,
                COALESCE((i.total_poin_inflow / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v,
                /* Rumus: (Total Poin + Total Ongkos) / Total M3 Keluar */
                COALESCE(((i.total_poin_inflow + o.total_ongkos_pkj) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_v_ongkos,
                /* Rumus VOP: (Total Poin + Total Ongkos + Total Penyusutan) / Total M3 Keluar */
                COALESCE(((i.total_poin_inflow + o.total_ongkos_pkj + o.total_penyusutan) / NULLIF(o.total_m3_keluar, 0)), 0) as total_harga_vop
            FROM REKAP_INFLOW i, REKAP_OUTFLOW o
        ";

        $result = \DB::select($sql, [
            'bulan1' => $bulan, 'tahun1' => $tahun,
            'bulan2' => $bulan, 'tahun2' => $tahun
        ]);

        return $result[0] ?? null;
    }    private function stitchBatchWithOutflow(array $tempGroup): array
    {
        $records = collect($tempGroup);
        $first = $records->first();
        $last = $records->first(fn($i) => $i->jumlah_batang > 0);

        // ! ONGKOS PEKERJA
        $ongkosPekerja = HargaPegawai::first()
            ->value('harga') ?? 0;
        // !

        $idsPenggunaanLahan = $records->pluck('id')->toArray();

        // SOLUSI 3: Eager Loading dengan pembatasan kolom pada relasi
        $outflowData = DetailHasilPaletRotary::with([
            'produksi:id,tgl_produksi,id_mesin',
            'produksi.mesin:id,nama_mesin,penyusutan',
            'produksi.detailPegawaiRotary:id,id_produksi',
            'setoranPaletUkuran:id,panjang,lebar,tebal'
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

        $produksiIds = $outflowData->pluck('id_produksi')->unique()->toArray();

        $totalOutputHarian = DetailHasilPaletRotary::whereIn('id_produksi', $produksiIds)
            ->with('setoranPaletUkuran')
            ->get()
            ->groupBy('id_produksi')
            ->map(function ($details) {
                return $details->sum(function ($d) {
                    $u = $d->setoranPaletUkuran;
                    return $u ? ($u->panjang * $u->lebar * $u->tebal * $d->total_lembar) / 10_000_000 : 0;
                });
            });

        $groupedOutflow = $outflowData->map(function ($hasil) use ($ongkosPekerja, $totalOutputHarian) {
            $produksi = $hasil->produksi;
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) ($hasil->total_lembar ?? 0);

            // Perbaikan pembagi kubikasi agar akurat (10^9 untuk mm ke m3)
            $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10_000_000 : 0;
            $m3TotalAllLahan = $totalOutputHarian[$hasil->id_produksi];
            $pekerja = ($produksi->detailPegawaiRotary->count() ?? 0);

            $msa = $pekerja * ($m3 / $m3TotalAllLahan);
            $calculatePekerja = round($msa) == 0 ? 1 : round($msa);
            $penyusutan = $produksi->mesin->penyusutan ?? 0;

            return [
                'tgl' => Carbon::parse($produksi->tgl_produksi)->format('d-m-Y'),
                'mesin' => $produksi->mesin->nama_mesin ?? 'Unknown',
                'jam_kerja' => "06:00 - 16:00",
                'ukuran' => $ukuran ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'pekerja' => (string) $calculatePekerja . " Orang",
                'ongkos' => $calculatePekerja * $ongkosPekerja,
                'penyusutan' => $penyusutan,
                'panjang' => $ukuran->panjang,
                'lebar' => $ukuran->lebar,
                'tebal' => $ukuran->tebal,
            ];
        })->groupBy(fn($item) => $item['tgl'] . $item['mesin'] . $item['ukuran'])
            ->map(fn($group) => [
                'tgl' => $group[0]['tgl'],
                'mesin' => $group[0]['mesin'],
                'jam_kerja' => $group[0]['jam_kerja'],
                'ukuran' => $group[0]['ukuran'],
                'total_banyak' => $group->sum('banyak'),
                'total_kubikasi' => number_format($group->sum('kubikasi'), 4),
                'pekerja' => $group[0]['pekerja'],
                'ongkos' => $group[0]['ongkos'],
                'penyusutan' => $group[0]['penyusutan'],
                'panjang' => $group[0]['panjang'],
                'lebar' => $group[0]['lebar'],
                'tebal' => $group[0]['tebal'],

            ])->values()->toArray();

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'grand_total_outflow_m3' => collect($groupedOutflow)->sum('total_kubikasi'),
            'grand_total_outflow_ongkos_pkj' => collect($groupedOutflow)->sum('ongkos'),
            'grand_total_outflow_penyusutan' => collect($groupedOutflow)->sum('penyusutan'),
            'outflow_detail' => $groupedOutflow,
            'info' => [
                'lahan' => $first->lahan->nama_lahan ?? '-',
                'kode' => $first->lahan->kode_lahan ?? '-',
                'jenis_kayu' => $first->jenisKayu->nama_kayu ?? '-',
                'kode_kayu' => $first->jenisKayu->kode_kayu ?? '-',
                'status' => $last ? 'SELESAI' : 'PROSES',
                'tgl_buka_lahan' => $first->created_at->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $last ? $last->created_at->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $last ? $last->jumlah_batang : 0,
            ],
        ];
    }


    private function getInflowByWindow($idLahan, $start, $end, $statusBatch)
    {
        // SOLUSI 3: Batasi kolom pada Inflow
        $query = NotaKayu::select('id', 'created_at', 'id_kayu_masuk', 'status')
            ->with([
                'kayuMasuk:id,seri',
                'kayuMasuk.detailTurusanKayus' => fn($q) => $q->where('lahan_id', $idLahan)
            ])
            ->where('status', 'like', '%Sudah Diperiksa%')
            ->whereHas('kayuMasuk.detailTurusanKayus', fn($q) => $q->where('lahan_id', $idLahan));

        $batasAtas = ($statusBatch === 'PROSES') ? now() : $end;
        $query->where('created_at', '<=', $batasAtas);
        if ($start) {
            $query->where('created_at', '>', $start);
        }

        return $query->get()->map(function ($nota) use ($idLahan) {
            $kayuMasukId = $nota->id_kayu_masuk;

            // Perhitungan Tingkat SQL Tinggi untuk Kubikasi dan Poin

            $totals = DetailTurusanKayu::query()
                ->where('detail_turusan_kayus.id_kayu_masuk', $kayuMasukId)
                ->where('detail_turusan_kayus.lahan_id', $idLahan)
                ->leftJoin('harga_kayus', function ($join) {
                    $join->on('detail_turusan_kayus.jenis_kayu_id', '=', 'harga_kayus.id_jenis_kayu')
                        ->on('detail_turusan_kayus.grade', '=', 'harga_kayus.grade')
                        ->on('detail_turusan_kayus.panjang', '=', 'harga_kayus.panjang')
                        ->whereColumn('detail_turusan_kayus.diameter', '>=', 'harga_kayus.diameter_terkecil')
                        ->whereColumn('detail_turusan_kayus.diameter', '<=', 'harga_kayus.diameter_terbesar');
                })
                ->selectRaw("
                        SUM(detail_turusan_kayus.kuantitas) as total_qty,
                        
                        /* 1. KUBIKASI (Tetap pakai ROUND 4 desimal karena ini sudah FIX sebelumnya) */
                        SUM(
                            ROUND(
                                (CAST(detail_turusan_kayus.panjang AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) 
                                * CAST(detail_turusan_kayus.kuantitas AS DECIMAL(20,4)), 
                            4)
                        ) as total_kubikasi,

                        /* 2. POIN: Kita gunakan FLOOR untuk membuang desimal di setiap baris (Gaya Excel) */
                        SUM(
                            FLOOR(
                                (COALESCE(harga_kayus.harga_beli, 0) * ROUND(
                                    (CAST(detail_turusan_kayus.panjang AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) 
                                    * CAST(detail_turusan_kayus.kuantitas AS DECIMAL(20,4)), 
                                4)
                                ) * 1000
                            )
                        ) as total_poin,

                        COUNT(CASE WHEN harga_kayus.harga_beli IS NULL THEN 1 END) as harga_kosong_count
                    ")
                ->first();
            return [
                'tanggal' => $nota->created_at->format('d-m-Y'),
                'seri' => ($totals->harga_kosong_count > 0)
                    ? $nota->kayuMasuk->seri . " ⚠️ (Harga Belum Atur: $totals->harga_kosong_count Baris)"
                    : $nota->kayuMasuk->seri,
                'banyak' => (int) $totals->total_qty,
                'kubikasi' => (float) $totals->total_kubikasi,
                'poin' => (float) $totals->total_poin // Poin sudah sinkron dengan database & excel
            ];
        });
    }

    private function calculatePoin($item)
    {
        $harga = $this->getHargaSatuan($item->id_jenis_kayu ?? 1, $item->grade ?? 0, $item->panjang ?? 0, $item->diameter);
        return (float) (($harga ?? 0) * $item->kubikasi * 1000);
    }

    private function getHargaSatuan($idJenisKayu, $grade, $panjang, $diameter)
    {
        return HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->where('diameter_terkecil', '<=', $diameter)
            ->where('diameter_terbesar', '>=', $diameter)
            ->orderBy('diameter_terkecil', 'desc')
            ->value('harga_beli') ?? 0;
    }
}