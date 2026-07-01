<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailTurusanKayu;
use App\Models\HargaKayu;
use App\Models\HargaPegawai;
use App\Models\HppAverageLog;
use App\Models\NotaKayu;
use App\Models\PenggunaanLahanRotary;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ProduksiInflowService
{
    public function getLaporanBatch($month = null, $year = null, $nama_lahan = 'Semua Lahan', $perPage = 10)
    {
        $query = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu',
        ])
            ->where('jumlah_batang', '>', 0);

        // Tambahkan Filter Tanggal
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($nama_lahan !== 'Semua Lahan') {
            $query->whereHas('lahan', function ($query) use ($nama_lahan) {
                $query->where('nama_lahan', $nama_lahan);
            });
        }
        $allClosures = $query->orderBy('created_at', 'desc')->get();
        $laporanFinal = [];

        foreach ($allClosures as $closure) {
            // Cari penutup terakhir sebelum batch ini untuk lahan yang sama
            $lastClosure = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('created_at', '<', $closure->created_at)
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang
            // Cari baris yang id_lahan & id_jenis_kayu sama, dan waktu <= penutup saat ini
            // namun > penutup sebelumnya (atau ambil semua yang belum punya penutup lain)
            $batchRecordsQuery = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc');

            if ($lastClosure) {
                $batchRecordsQuery->where('created_at', '>', $lastClosure->created_at);
            }

            $batchRecords = $batchRecordsQuery->get();

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

            $end = $closure->created_at;
            [$start, $notaIds] = $this->calculateInflowBoundaries($closure, $lastClosure);

            $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status'], $closure->id_jenis_kayu, $notaIds);

            $tglInflowPertama = $dataMasuk->min('tanggal');
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;
            $total_poin = number_format($dataMasuk->sum('poin'), 0, ',', '.');
            $harga_v_ongkos = $batch['grand_total_outflow_m3'] > 0
                ? (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj']) / $batch['grand_total_outflow_m3'])
                : 0.0;
            $harga_v_ongkos_penyusutan = $batch['grand_total_outflow_m3'] > 0
                ? (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj'] + $batch['grand_total_outflow_penyusutan']) / $batch['grand_total_outflow_m3'])
                : 0.0;

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
                    'jenis_kayu' => $jenis_kayu ? 'KAYU 260' : 'KAYU 130',
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => $dataMasuk->sum('kubikasi'),
                    'total_keluar_m3' => (float) number_format($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => $total_poin,
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? number_format(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2).'%'
                        : '0%',
                    'harga_veneer' => $batch['grand_total_outflow_m3'] > 0
                        ? (float) ($dataMasuk->sum('poin') / $batch['grand_total_outflow_m3'])
                        : 0.0,
                    'harga_v_ongkos' => $harga_v_ongkos,
                    'harga_vop' => $harga_v_ongkos_penyusutan,
                ],
            ];
        }

        // Merge zero inflow batches and maintain descending order
        $laporanFinal = $this->mergeZeroInflowBatches($laporanFinal, true);

        // Paginate manually
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($laporanFinal);
        $slice = $itemCollection->slice(($currentPage - 1) * $perPage, $perPage)->values()->all();

        // Kembalikan objek paginator agar view bisa merender links()
        return new LengthAwarePaginator(
            $slice,
            $itemCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => url()->previous(),
                'query' => request()->query(),
            ]
        );
    }

    public function getLaporanBatchPreview($bulan = null, $tahun = null, $lahan = 'Semua Lahan', $perPage = 10)
    {
        $bulan = $bulan ?: date('m');
        $tahun = $tahun ?: date('y');
        $lahanX = $this->getActiveLahanSheets($bulan, $tahun)[0] ?? null;
        if (! isset($lahan)) {
            $lahan = $lahanX;
        }

        $paginatedClosures = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu',
        ])
            ->whereHas('lahan', function ($query) use ($lahan) {
                $query->where('nama_lahan', $lahan);
            })
            ->where('jumlah_batang', '>', 0)
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->orderBy('created_at', 'asc')
            ->get();

        $laporanFinal = [];

        foreach ($paginatedClosures as $closure) {
            // Cari penutup terakhir sebelum batch ini untuk lahan yang sama
            $lastClosure = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('created_at', '<', $closure->created_at)
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang
            // Cari baris yang id_lahan & id_jenis_kayu sama, dan waktu <= penutup saat ini
            // namun > penutup sebelumnya (atau ambil semua yang belum punya penutup lain)
            $batchRecordsQuery = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc');

            if ($lastClosure) {
                $batchRecordsQuery->where('created_at', '>', $lastClosure->created_at);
            }

            $batchRecords = $batchRecordsQuery->get();

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

            $end = $closure->created_at;
            [$start, $notaIds] = $this->calculateInflowBoundaries($closure, $lastClosure);

            $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status'], $closure->id_jenis_kayu, $notaIds);

            $tglInflowPertama = $dataMasuk->min('tanggal');
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;
            $total_poin = number_format($dataMasuk->sum('poin'), 0, ',', '.');
            $harga_v_ongkos = $batch['grand_total_outflow_m3'] > 0
                ? (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj']) / $batch['grand_total_outflow_m3'])
                : 0.0;
            $harga_v_ongkos_penyusutan = $batch['grand_total_outflow_m3'] > 0
                ? (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj'] + $batch['grand_total_outflow_penyusutan']) / $batch['grand_total_outflow_m3'])
                : 0.0;

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
                    'jenis_kayu' => $jenis_kayu ? 'KAYU 260' : 'KAYU 130',
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => $dataMasuk->sum('kubikasi'),
                    'total_keluar_m3' => (float) number_format($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => $total_poin,
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? number_format(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2).'%'
                        : '0%',
                    'harga_veneer' => $batch['grand_total_outflow_m3'] > 0
                        ? (float) ($dataMasuk->sum('poin') / $batch['grand_total_outflow_m3'])
                        : 0.0,
                    'harga_v_ongkos' => $harga_v_ongkos,
                    'harga_vop' => $harga_v_ongkos_penyusutan,
                ],
            ];
        }

        // Merge zero inflow batches and maintain ascending order
        $laporanFinal = $this->mergeZeroInflowBatches($laporanFinal, false);

        // Kembalikan objek paginator agar view bisa merender links()
        return collect($laporanFinal);
    }

    public function getSummaryLaporanLahan($laporanFinalCollection)
    {

        $totalMasukM3 = $laporanFinalCollection->sum('summary.total_masuk_m3');
        $totalKeluarM3 = $laporanFinalCollection->sum('summary.total_keluar_m3');
        $totalHargaVeneer = $laporanFinalCollection->avg('summary.harga_veneer');

        return [
            'total_kayu_masuk' => $laporanFinalCollection->sum('summary.total_kayu_masuk') ?? 0,
            'total_kubikasi_kayu_masuk' => $totalMasukM3 ?? 0,
            'total_poin_masuk' => $laporanFinalCollection->sum(function ($item) {
                // Menghapus format ribuan agar bisa dijumlahkan sebagai angka
                return (float) str_replace(['.', ','], ['', '.'], $item['summary']['total_poin']);
            }) ?? 0,
            'total_kubikasi_veneer' => $totalKeluarM3 ?? 0,
            'rata_rata_rendemen' => $totalMasukM3 > 0
                ? number_format(($totalKeluarM3 / $totalMasukM3) * 100, 2).'%'
                : '0%',
            'total_harga_veneer' => $totalHargaVeneer ?? 0,
            'total_harga_v_ongkos' => $laporanFinalCollection->avg('summary.harga_v_ongkos') ?? 0,
            'total_harga_vop' => $laporanFinalCollection->avg('summary.harga_vop') ?? 0,
        ];
    }

    private function stitchBatchWithOutflow(array $tempGroup): array
    {
        $records = collect($tempGroup);
        $first = $records->first();
        $last = $records->first(fn ($i) => $i->jumlah_batang > 0);

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
            'setoranPaletUkuran:id,panjang,lebar,tebal',
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
            $m3TotalAllLahan = isset($totalOutputHarian[$hasil->id_produksi]) ? (float) $totalOutputHarian[$hasil->id_produksi] : 0.0;
            $pekerja = $produksi ? ($produksi->detailPegawaiRotary ? $produksi->detailPegawaiRotary->count() : 0) : 0;

            $msa = ($m3TotalAllLahan > 0) ? ($pekerja * ($m3 / $m3TotalAllLahan)) : 0.0;
            $calculatePekerja = max(1, round($msa * $pekerja));
            $penyusutan = ($produksi && $produksi->mesin) ? ($produksi->mesin->penyusutan ?? 0) : 0;

            return [
                'tgl' => $produksi ? Carbon::parse($produksi->tgl_produksi)->format('d-m-Y') : ($hasil->created_at ? Carbon::parse($hasil->created_at)->format('d-m-Y') : '-'),
                'mesin' => $produksi ? ($produksi->mesin ? ($produksi->mesin->nama_mesin ?? 'Unknown') : 'Unknown') : 'Unknown',
                'jam_kerja' => '06:00 - 16:00',
                'ukuran' => $ukuran ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'pekerja' => (string) $calculatePekerja.' Orang',
                'ongkos' => $calculatePekerja * $ongkosPekerja,
                'penyusutan' => $penyusutan,
                'panjang' => $ukuran->panjang,
                'lebar' => $ukuran->lebar,
                'tebal' => $ukuran->tebal,
            ];
        })->groupBy(fn ($item) => $item['tgl'].$item['mesin'].$item['ukuran'])
            ->map(fn ($group) => [
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

    private function getInflowByWindow($idLahan, $start, $end, $statusBatch, $idJenisKayu, $notaIds = [])
    {
        // SOLUSI 3: Batasi kolom pada Inflow, saring juga berdasarkan jenis kayu batch
        $query = NotaKayu::select('id', 'created_at', 'id_kayu_masuk', 'status')
            ->with([
                'kayuMasuk:id,seri',
                'kayuMasuk.detailTurusanKayus' => fn ($q) => $q->where('lahan_id', $idLahan)->where('jenis_kayu_id', $idJenisKayu),
            ])
            ->where('status', 'like', '%Sudah Diperiksa%');

        $batasAtas = ($statusBatch === 'PROSES') ? now() : $end;

        if (! empty($notaIds)) {
            $query->whereIn('id', $notaIds);
        } else {
            $query->whereHas('kayuMasuk.detailTurusanKayus', fn ($q) => $q->where('lahan_id', $idLahan)->where('jenis_kayu_id', $idJenisKayu));
            $query->where('created_at', '<=', $batasAtas);
            if ($start) {
                $query->where('created_at', '>', $start);
            }
        }

        $notas = $query->get();
        $notaInflows = collect();

        if (! $notas->isEmpty()) {
            $kayuMasukIds = $notas->pluck('id_kayu_masuk')->unique()->toArray();

            $totalsGrouped = DetailTurusanKayu::query()
                ->whereIn('detail_turusan_kayus.id_kayu_masuk', $kayuMasukIds)
                ->where('detail_turusan_kayus.lahan_id', $idLahan)
                ->where('detail_turusan_kayus.jenis_kayu_id', $idJenisKayu)
                ->leftJoin('harga_kayus', function ($join) {
                    $join->on('detail_turusan_kayus.jenis_kayu_id', '=', 'harga_kayus.id_jenis_kayu')
                        ->on('detail_turusan_kayus.grade', '=', 'harga_kayus.grade')
                        ->on('detail_turusan_kayus.panjang', '=', 'harga_kayus.panjang')
                        ->whereColumn('detail_turusan_kayus.diameter', '>=', 'harga_kayus.diameter_terkecil')
                        ->whereColumn('detail_turusan_kayus.diameter', '<=', 'harga_kayus.diameter_terbesar');
                })
                ->selectRaw('
                        detail_turusan_kayus.id_kayu_masuk,
                        SUM(detail_turusan_kayus.kuantitas) as total_qty,
                        SUM(
                            ROUND(
                                (CAST(detail_turusan_kayus.panjang AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * CAST(detail_turusan_kayus.diameter AS DECIMAL(20,4)) * 0.785 / 1000000) 
                                * CAST(detail_turusan_kayus.kuantitas AS DECIMAL(20,4)), 
                            4)
                        ) as total_kubikasi,
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
                    ')
                ->groupBy('detail_turusan_kayus.id_kayu_masuk')
                ->get()
                ->keyBy('id_kayu_masuk');

            $notaInflows = $notas->map(function ($nota) use ($totalsGrouped) {
                $kayuMasukId = $nota->id_kayu_masuk;
                $totals = $totalsGrouped->get($kayuMasukId);

                $totalQty = $totals ? (int) $totals->total_qty : 0;
                $totalKubikasi = $totals ? (float) $totals->total_kubikasi : 0.0;
                $totalPoin = $totals ? (float) $totals->total_poin : 0.0;
                $hargaKosongCount = $totals ? (int) $totals->harga_kosong_count : 0;

                return [
                    'tanggal' => $nota->created_at->format('d-m-Y'),
                    'seri' => ($hargaKosongCount > 0)
                        ? $nota->kayuMasuk->seri." ⚠️ (Harga Belum Atur: $hargaKosongCount Baris)"
                        : $nota->kayuMasuk->seri,
                    'banyak' => $totalQty,
                    'kubikasi' => $totalKubikasi,
                    'poin' => $totalPoin,
                ];
            })->filter(fn ($x) => $x['banyak'] > 0)->values();
        }

        // Ambil Data Stok Opname dari HppAverageLog
        $opnameQuery = HppAverageLog::where('id_lahan', $idLahan)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('keterangan', 'like', 'STOK OPNAME%')
            ->where('created_at', '<=', $batasAtas);

        if ($start) {
            $opnameQuery->where('created_at', '>', $start);
        }

        $opnames = $opnameQuery->get();

        $opnameInflows = $opnames->map(function ($log) {
            $isMasuk = $log->tipe_transaksi === 'masuk';
            $multiplier = $isMasuk ? 1 : -1;

            $parts = explode('|', $log->keterangan);
            $notes = isset($parts[1]) ? trim($parts[1]) : 'Koreksi Stok';

            return [
                'tanggal' => $log->created_at->format('d-m-Y'),
                'seri' => '⚙️ OPNAME: '.$notes,
                'banyak' => $log->total_batang * $multiplier,
                'kubikasi' => (float) $log->total_kubikasi * $multiplier,
                'poin' => (float) $log->nilai_stok * $multiplier,
            ];
        });

        return $notaInflows->concat($opnameInflows);
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

    public function getActiveLahanSheets($bulan = null, $tahun = null)
    {
        $bulan = $bulan ?: date('m');
        $tahun = $tahun ?: date('Y');

        $paginatedClosures = PenggunaanLahanRotary::whereHas('lahan')
            ->where('jumlah_batang', '>', 0)
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->get()
            ->pluck('lahan.nama_lahan')
            ->unique()
            ->values()
            ->toArray();

        return $paginatedClosures;
    }

    private function getBatchStart($closure)
    {
        if (! $closure) {
            return null;
        }
        $batchRecords = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
            ->where('id_jenis_kayu', $closure->id_jenis_kayu)
            ->where('created_at', '<=', $closure->created_at)
            ->orderBy('created_at', 'desc')
            ->get();

        $tempGroup = [];
        foreach ($batchRecords as $record) {
            $tempGroup[] = $record;
            if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                array_pop($tempGroup);
                break;
            }
        }
        $tempGroup = array_reverse($tempGroup);

        return $tempGroup[0] ? $tempGroup[0]->created_at : $closure->created_at;
    }

    private function mergeZeroInflowBatches(array $laporanFinal, $descending = false): array
    {
        // 1. Urutkan secara kronologis (ASC) berdasarkan waktu buka lahan
        usort($laporanFinal, function ($a, $b) {
            return strcmp($a['batch_info']['tgl_buka_lahan'], $b['batch_info']['tgl_buka_lahan']);
        });

        $mergedList = [];

        foreach ($laporanFinal as $item) {
            $totalMasuk = (float) $item['summary']['total_masuk_m3'];
            $totalKeluar = (float) $item['summary']['total_keluar_m3'];

            // Jika batch memiliki 0 kayu masuk tetapi memiliki kayu keluar (outflow),
            // ini adalah kelanjutan dari batch sebelumnya pada lahan & jenis kayu yang sama.
            if ($totalMasuk == 0 && $totalKeluar > 0) {
                $foundParentKey = null;
                for ($i = count($mergedList) - 1; $i >= 0; $i--) {
                    if ($mergedList[$i]['batch_info']['lahan'] === $item['batch_info']['lahan'] &&
                        $mergedList[$i]['batch_info']['jenis_kayu'] === $item['batch_info']['jenis_kayu']) {
                        $foundParentKey = $i;
                        break;
                    }
                }

                if ($foundParentKey !== null) {
                    $parent = &$mergedList[$foundParentKey];

                    // Gabungkan Outflow
                    $parent['outflow'] = array_merge($parent['outflow'], $item['outflow']);

                    // Hitung ulang grand total outflow
                    $totalOutflowM3 = collect($parent['outflow'])->sum(fn ($x) => (float) str_replace(',', '', $x['total_kubikasi']));
                    $totalOngkos = collect($parent['outflow'])->sum('ongkos');
                    $totalPenyusutan = collect($parent['outflow'])->sum('penyusutan');

                    // Update summary
                    $parent['summary']['total_keluar_m3'] = (float) number_format($totalOutflowM3, 4);

                    $totalInflowM3 = (float) $parent['summary']['total_masuk_m3'];
                    $parent['summary']['rendemen'] = $totalInflowM3 > 0
                        ? number_format(($totalOutflowM3 / $totalInflowM3) * 100, 2).'%'
                        : '0%';

                    $totalPoinVal = (float) str_replace(['.', ','], ['', '.'], $parent['summary']['total_poin']);

                    $parent['summary']['harga_veneer'] = $totalOutflowM3 > 0
                        ? (float) ($totalPoinVal / $totalOutflowM3)
                        : 0.0;

                    $parent['summary']['harga_v_ongkos'] = $totalOutflowM3 > 0
                        ? (float) (($totalPoinVal + $totalOngkos) / $totalOutflowM3)
                        : 0.0;

                    $parent['summary']['harga_vop'] = $totalOutflowM3 > 0
                        ? (float) (($totalPoinVal + $totalOngkos + $totalPenyusutan) / $totalOutflowM3)
                        : 0.0;

                    // Update info batch ke status penutupan terakhir
                    $parent['batch_info']['tgl_tutup_lahan'] = $item['batch_info']['tgl_tutup_lahan'];
                    $parent['batch_info']['jumlah_batang_akhir'] = $item['batch_info']['jumlah_batang_akhir'];
                    $parent['batch_info']['status'] = $item['batch_info']['status'];

                    continue;
                }
            }

            $mergedList[] = $item;
        }

        // 2. Kembalikan ke urutan menurun (DESC) jika dipanggil oleh laporan utama
        // 2. Urutkan berdasarkan tanggal kayu keluar (tgl_tutup_lahan)
        if ($descending) {
            usort($mergedList, function ($a, $b) {

                $tglA = $a['batch_info']['tgl_tutup_lahan'] === 'MASIH BERJALAN'
                    ? 0
                    : strtotime($a['batch_info']['tgl_tutup_lahan']);

                $tglB = $b['batch_info']['tgl_tutup_lahan'] === 'MASIH BERJALAN'
                    ? 0
                    : strtotime($b['batch_info']['tgl_tutup_lahan']);

                return $tglB <=> $tglA;
            });
        }

        return $mergedList;
    }

    private function calculateInflowBoundaries($closure, $lastClosure)
    {
        $start = $lastClosure ? $lastClosure->created_at : null;
        $notaIds = [];

        $currentClosureLog = HppAverageLog::where('referensi_type', PenggunaanLahanRotary::class)
            ->where('referensi_id', $closure->id)
            ->first();

        if ($currentClosureLog) {
            $lastClosureLog = HppAverageLog::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('tipe_transaksi', 'keluar')
                ->where('created_at', '<', $currentClosureLog->created_at)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastClosureLog) {
                $prevClosure = PenggunaanLahanRotary::find($lastClosureLog->referensi_id);
                if ($prevClosure) {
                    $start = $prevClosure->created_at;
                }
            } else {
                $masukLogsQuery = HppAverageLog::where('id_lahan', $closure->id_lahan)
                    ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                    ->where('tipe_transaksi', 'masuk')
                    ->where('referensi_type', NotaKayu::class)
                    ->where('created_at', '<', $currentClosureLog->created_at);

                $notaIds = $masukLogsQuery->pluck('referensi_id')->toArray();

                // Hitung berapa banyak qty yang sudah tercatat di HPP masuk
                $trackedQty = 0;
                if (! empty($notaIds)) {
                    $trackedQty = DetailTurusanKayu::where('lahan_id', $closure->id_lahan)
                        ->where('jenis_kayu_id', $closure->id_jenis_kayu)
                        ->whereIn('id_kayu_masuk', function ($q) use ($notaIds) {
                            $q->select('id_kayu_masuk')->from('nota_kayus')->whereIn('id', $notaIds);
                        })
                        ->sum('kuantitas');
                }

                // Sisa qty yang merupakan saldo awal (pre-HPP)
                $untrackedQty = $currentClosureLog->total_batang - $trackedQty;

                if ($untrackedQty > 0) {
                    $minNotaCreatedAt = null;
                    if (! empty($notaIds)) {
                        $minNotaCreatedAt = NotaKayu::whereIn('id', $notaIds)->min('created_at');
                    }
                    $firstHppTime = $minNotaCreatedAt ?: $currentClosureLog->created_at;

                    // Query NotaKayu sebelum HPP secara descending
                    $preHppNotas = NotaKayu::where('status', 'like', '%Sudah Diperiksa%')
                        ->where('created_at', '<', $firstHppTime)
                        ->whereHas('kayuMasuk.detailTurusanKayus', function ($q) use ($closure) {
                            $q->where('lahan_id', $closure->id_lahan)
                                ->where('jenis_kayu_id', $closure->id_jenis_kayu);
                        })
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $accumulated = 0;
                    $preHppNotaIds = [];
                    foreach ($preHppNotas as $n) {
                        if ($accumulated >= $untrackedQty) {
                            break;
                        }
                        $qty = DetailTurusanKayu::where('id_kayu_masuk', $n->id_kayu_masuk)
                            ->where('lahan_id', $closure->id_lahan)
                            ->where('jenis_kayu_id', $closure->id_jenis_kayu)
                            ->sum('kuantitas');

                        $preHppNotaIds[] = $n->id;
                        $accumulated += $qty;
                    }

                    $notaIds = array_merge($notaIds, $preHppNotaIds);
                }

                // Set start boundary to the oldest nota in the merged list, with a subDays(30) fallback if empty
                if (! empty($notaIds)) {
                    $minNotaCreatedAt = NotaKayu::whereIn('id', $notaIds)->min('created_at');
                    if ($minNotaCreatedAt) {
                        $start = Carbon::parse($minNotaCreatedAt)->subDays(30);
                    }
                }
            }
        }

        return [$start, $notaIds];
    }
}
