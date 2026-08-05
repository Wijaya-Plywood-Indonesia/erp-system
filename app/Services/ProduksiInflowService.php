<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailTurusanKayu;
use App\Models\HargaKayu;
use App\Models\HargaPegawai;
use App\Models\HppAverageLog;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Models\Mesin;
use App\Models\NotaKayu;
use App\Models\PenggunaanLahanRotary;
use App\Models\Ukuran;
use App\Traits\CachesLookupModels;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ProduksiInflowService
{
    use CachesLookupModels;

    /**
     * OPTIMASI #1: cache seluruh baris PenggunaanLahanRotary (dengan jumlah_batang > 0)
     * per id_lahan, diambil SEKALI per id_lahan lalu difilter di memori (bukan query
     * ulang ke DB tiap kali butuh "lastClosure" untuk closure yang berbeda-beda).
     *
     * Struktur: [id_lahan => Collection<PenggunaanLahanRotary>] terurut ASC by created_at
     */
    private array $closuresByLahanCache = [];

    /**
     * OPTIMASI #2: cache seluruh baris PenggunaanLahanRotary (SEMUA, bukan cuma closure)
     * per kombinasi id_lahan + id_jenis_kayu, dengan relasi lahan & jenisKayu SUDAH
     * di-eager-load supaya stitchBatchWithOutflow() tidak lazy-load per baris.
     *
     * Struktur: ["idLahan-idJenisKayu" => Collection<PenggunaanLahanRotary>] terurut DESC
     */
    private array $recordsByLahanJenisCache = [];

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
            $laporanFinal[] = $this->buildLaporanItemForClosure($closure);
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
            $laporanFinal[] = $this->buildLaporanItemForClosure($closure);
        }

        // Merge zero inflow batches and maintain ascending order
        $laporanFinal = $this->mergeZeroInflowBatches($laporanFinal, false);

        // SORT FINAL: urutkan berdasarkan tanggal yang BENAR-BENAR tampil di kolom "Tanggal"
        // pada laporan/export, yaitu tanggal OUTFLOW (produksi veneer / $item['outflow'][*]['tgl']),
        // BUKAN tgl_buka_lahan (tanggal inflow kayu masuk). Di dalam satu batch, baris outflow
        // sudah terurut ascending (lihat stitchBatchWithOutflow), jadi tanggal terkecil dari
        // kumpulan outflow itulah yang dipakai sebagai kunci urut antar-batch.
        // Kalau batch tidak punya outflow sama sekali, fallback ke tgl_buka_lahan.
        $laporanFinal = collect($laporanFinal)
            ->sortBy(function ($item) {
                $outflowDates = collect($item['outflow'])
                    ->pluck('tgl')
                    ->filter()
                    ->map(fn ($tgl) => Carbon::createFromFormat('d-m-Y', $tgl));

                return $outflowDates->isNotEmpty()
                    ? $outflowDates->min()
                    : Carbon::parse($item['batch_info']['tgl_buka_lahan']);
            })
            ->values()
            ->all();

        // Kembalikan objek paginator agar view bisa merender links()
        return collect($laporanFinal);
    }

    /**
     * Logic pembangunan 1 baris laporan untuk 1 closure, DIEKSTRAK dari
     * getLaporanBatch() & getLaporanBatchPreview() supaya tidak duplikat kode
     * (sebelumnya kedua method ini punya blok logic yang identik).
     * Tidak ada perubahan logic bisnis di sini — cuma dipindah jadi method sendiri.
     */
    private function buildLaporanItemForClosure(PenggunaanLahanRotary $closure): array
    {
        // Cari penutup terakhir sebelum batch ini untuk lahan yang sama.
        // OPTIMASI: diambil dari cache in-memory (getClosuresForLahan), bukan query DB.
        $lastClosure = $this->getClosuresForLahan($closure->id_lahan)
            ->filter(fn ($r) => $r->created_at->lt($closure->created_at))
            ->last(); // Collection sudah terurut ASC, jadi last() = paling akhir sebelum $closure

        // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang.
        // OPTIMASI: diambil dari cache in-memory (getRecordsForLahanJenis), bukan query DB.
        $batchRecords = $this->getRecordsForLahanJenis($closure->id_lahan, $closure->id_jenis_kayu)
            ->filter(function ($r) use ($closure, $lastClosure) {
                if ($r->created_at->gt($closure->created_at)) {
                    return false;
                }
                if ($lastClosure && ! $r->created_at->gt($lastClosure->created_at)) {
                    return false;
                }

                return true;
            })
            ->values(); // sudah terurut DESC dari cache

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

        // Cari tanggal inflow paling awal dengan PARSING tanggal (bukan min() string biasa),
        // karena format 'd-m-Y' (hari di depan) akan salah urut kalau dibandingkan sebagai teks
        // lintas bulan (mis. "02-06-2026" vs "28-05-2026").
        $tglInflowPertamaCarbon = $dataMasuk->isNotEmpty()
            ? $dataMasuk->map(fn ($item) => Carbon::createFromFormat('d-m-Y', $item['tanggal']))->min()
            : null;
        $tglBukaFix = $tglInflowPertamaCarbon
            ? $tglInflowPertamaCarbon->format('Y-m-d H:i:s')
            : $batch['info']['tgl_buka_lahan'];

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

        return [
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

    /**
     * OPTIMASI: ambil SEMUA baris PenggunaanLahanRotary (jumlah_batang > 0) untuk
     * 1 id_lahan, SEKALI query, di-cache untuk sisa request ini. Dipakai untuk
     * mencari "lastClosure" tanpa perlu query DB berulang per closure.
     */
    private function getClosuresForLahan($idLahan)
    {
        if (! array_key_exists($idLahan, $this->closuresByLahanCache)) {
            $this->closuresByLahanCache[$idLahan] = PenggunaanLahanRotary::where('id_lahan', $idLahan)
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        return $this->closuresByLahanCache[$idLahan];
    }

    /**
     * OPTIMASI: ambil SEMUA baris PenggunaanLahanRotary (apapun jumlah_batang-nya)
     * untuk 1 kombinasi id_lahan + id_jenis_kayu, SEKALI query, di-cache untuk sisa
     * request ini.
     *
     * CATATAN PENTING: relasi 'lahan' & 'jenisKayu' SENGAJA TIDAK di-eager-load lewat
     * with() di sini. Method ini di-cache PER KOMBINASI id_lahan+id_jenis_kayu, jadi
     * kalau 1 lahan punya beberapa jenis kayu berbeda, with('lahan') akan tetap
     * ke-query ulang untuk id_lahan yang SAMA setiap kali kombinasi jenis_kayu-nya beda
     * (karena tiap panggilan adalah query baru dari nol). Makanya di bawah kita pakai
     * setRelation() manual dengan $this->cached() — supaya 1 ID lahan / jenis kayu
     * betul-betul cuma di-query SEKALI untuk SELURUH request, terlepas dari berapa
     * kali method ini dipanggil dengan kombinasi id_jenis_kayu yang berbeda-beda.
     */
    private function getRecordsForLahanJenis($idLahan, $idJenisKayu)
    {
        $key = $idLahan.'-'.$idJenisKayu;

        if (! array_key_exists($key, $this->recordsByLahanJenisCache)) {
            $records = PenggunaanLahanRotary::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->orderBy('created_at', 'desc')
                ->get();

            // Ambil 1x dari cache trait (dedup lintas semua pemanggilan method ini,
            // bukan cuma dalam 1 pemanggilan seperti with() biasa).
            $lahan = $this->cached(
                Lahan::class,
                $idLahan,
                ['id', 'nama_lahan', 'kode_lahan']
            );
            $jenisKayu = $this->cached(
                JenisKayu::class,
                $idJenisKayu,
                ['id', 'nama_kayu', 'kode_kayu']
            );

            // Tempelkan relasi secara manual (tanpa trigger query tambahan sama sekali,
            // karena $lahan & $jenisKayu di atas sudah diambil dari cache/DB duluan).
            foreach ($records as $record) {
                $record->setRelation('lahan', $lahan);
                $record->setRelation('jenisKayu', $jenisKayu);
            }

            $this->recordsByLahanJenisCache[$key] = $records;
        }

        return $this->recordsByLahanJenisCache[$key];
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
        // OPTIMASI: HargaPegawai::first() nilainya SAMA untuk seluruh request ini
        // (tidak bergantung parameter apapun), jadi cukup query SEKALI lalu dipakai
        // ulang. Sebelumnya method ini query harga_pegawais setiap kali dipanggil
        // (yaitu SETIAP batch/closure), padahal hasilnya selalu identik.
        $ongkosPekerja = $this->cachedSingle('harga_pegawai', function () {
            return HargaPegawai::first()->value('harga') ?? 0;
        });
        // !

        $idsPenggunaanLahan = $records->pluck('id')->toArray();

        // OPTIMASI #3: TIDAK lagi eager-load 'produksi.mesin' & 'setoranPaletUkuran'
        // lewat with(). Sebelumnya, dengan with(), Laravel query ulang ke tabel
        // `mesins`/`ukurans` SETIAP kali stitchBatchWithOutflow() dipanggil (yaitu
        // per closure/batch) — walaupun mesin/ukurannya sering ID yang SAMA lintas
        // batch (mis. mesin id=1 dipakai puluhan batch). Ini terlihat jelas di
        // query log sebagai puluhan baris "select ... from mesins where id in (1)"
        // dan "select ... from ukurans where id in (1)" yang berulang.
        //
        // Solusinya sama seperti Lahan & JenisKayu di getRecordsForLahanJenis():
        // pakai $this->cached() dari CachesLookupModels supaya 1 ID mesin/ukuran
        // betul-betul cuma di-query SEKALI untuk SELURUH request, lalu ditempel
        // manual via setRelation() (tidak trigger query tambahan).
        $outflowData = DetailHasilPaletRotary::with([
            'produksi:id,tgl_produksi,id_mesin',
            'produksi.detailPegawaiRotary:id,id_produksi',
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

        // Tempel relasi 'mesin' dari cache (dedup per ID mesin, lintas seluruh request)
        $mesinIds = $outflowData->pluck('produksi.id_mesin')->filter()->unique()->all();
        foreach ($mesinIds as $mesinId) {
            $this->cached(Mesin::class, $mesinId, ['id', 'nama_mesin', 'penyusutan']);
        }
        foreach ($outflowData as $hasil) {
            if ($hasil->produksi && $hasil->produksi->id_mesin) {
                $hasil->produksi->setRelation(
                    'mesin',
                    $this->cached(Mesin::class, $hasil->produksi->id_mesin, ['id', 'nama_mesin', 'penyusutan'])
                );
            }
        }

        // Tempel relasi 'setoranPaletUkuran' dari cache (dedup per ID ukuran).
        // Nama foreign key diambil secara dinamis dari definisi relasi (tidak
        // trigger query — cuma introspeksi objek relasi), supaya tidak hardcode
        // nama kolom FK yang mungkin berbeda antar-instalasi (mis. id_ukuran /
        // id_setoran_palet_ukuran). Kalau relasi ini bukan BelongsTo, ganti baris
        // di bawah dengan nama kolom FK yang sesuai secara langsung.
        $ukuranFk = (new DetailHasilPaletRotary)->setoranPaletUkuran()->getForeignKeyName();
        $ukuranIds = $outflowData->pluck($ukuranFk)->filter()->unique()->all();
        foreach ($ukuranIds as $ukuranId) {
            $this->cached(Ukuran::class, $ukuranId, ['id', 'panjang', 'lebar', 'tebal']);
        }
        foreach ($outflowData as $hasil) {
            $hasil->setRelation(
                'setoranPaletUkuran',
                $this->cached(Ukuran::class, $hasil->{$ukuranFk}, ['id', 'panjang', 'lebar', 'tebal'])
            );
        }

        $produksiIds = $outflowData->pluck('id_produksi')->unique()->toArray();

        // SOLUSI 3 (revisi): TIDAK lagi with('setoranPaletUkuran') di sini juga —
        // pakai cache Ukuran yang sama persis dari atas. Kalau ID ukurannya sudah
        // pernah di-cache (kemungkinan besar, karena ini produksi yang sama),
        // baris di bawah 0 query tambahan sama sekali.
        $totalOutputHarian = DetailHasilPaletRotary::whereIn('id_produksi', $produksiIds)
            ->get()
            ->each(function ($d) use ($ukuranFk) {
                $d->setRelation(
                    'setoranPaletUkuran',
                    $this->cached(Ukuran::class, $d->{$ukuranFk}, ['id', 'panjang', 'lebar', 'tebal'])
                );
            })
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
                // OPTIMASI: $first->lahan & $first->jenisKayu sekarang sudah di-eager-load
                // lewat getRecordsForLahanJenis() di atas, jadi baris ini TIDAK lagi
                // trigger query tambahan ke tabel `lahans` / `jenis_kayus` seperti sebelumnya.
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

        // OPTIMASI: eager-load 'lahan' SEBELUM pluck(), supaya
        // ->pluck('lahan.nama_lahan') tidak lazy-load ->lahan satu-satu
        // per baris (yang tadinya menyebabkan N query ke tabel `lahans`,
        // satu untuk tiap baris PenggunaanLahanRotary yang cocok filter).
        $paginatedClosures = PenggunaanLahanRotary::with('lahan:id,nama_lahan')
            ->whereHas('lahan')
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

    /**
     * Parse tanggal yang formatnya bisa berbeda-beda ('d-m-Y' ATAU 'Y-m-d H:i:s' / format lain
     * yang bisa dibaca Carbon::parse) menjadi objek Carbon yang bisa dibandingkan dengan aman.
     */
    private function parseTglFlexible($str)
    {
        if (empty($str) || $str === 'MASIH BERJALAN') {
            return Carbon::now();
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $str)->startOfDay();
        } catch (\Exception $e) {
            try {
                return Carbon::parse($str);
            } catch (\Exception $e2) {
                return Carbon::now();
            }
        }
    }

    private function mergeZeroInflowBatches(array $laporanFinal, $descending = false): array
    {
        // 1. Urutkan secara kronologis (ASC) berdasarkan waktu buka lahan
        // (pakai parsing Carbon, BUKAN strcmp, karena format tgl_buka_lahan
        // bisa campur antara 'd-m-Y' dan 'Y-m-d H:i:s')
        usort($laporanFinal, function ($a, $b) {
            $tglA = $this->parseTglFlexible($a['batch_info']['tgl_buka_lahan']);
            $tglB = $this->parseTglFlexible($b['batch_info']['tgl_buka_lahan']);

            return $tglA <=> $tglB;
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
                    if (
                        $mergedList[$i]['batch_info']['lahan'] === $item['batch_info']['lahan'] &&
                        $mergedList[$i]['batch_info']['jenis_kayu'] === $item['batch_info']['jenis_kayu']
                    ) {
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
                // OPTIMASI: pakai cache trait supaya id yang sama tidak di-query ulang ke DB.
                $prevClosure = $this->cached(PenggunaanLahanRotary::class, $lastClosureLog->referensi_id);
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
