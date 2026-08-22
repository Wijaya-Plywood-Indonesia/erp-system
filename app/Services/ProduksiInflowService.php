<?php

namespace App\Services;

use App\Models\BahanPenolongRotary;
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

    /**
     * OPTIMASI #4: cache SEMUA baris DetailHasilPaletRotary per id_produksi
     * (dengan relasi produksi/mesin/pegawai/ukuran sudah ditempel), supaya
     * stitchBatchWithOutflow() TIDAK query 2x ke detail_hasil_palet_rotaries
     * untuk id_produksi yang sama.
     *
     * PENTING: cache ini HANYA boleh diisi dari query yang LENGKAP (semua lahan)
     * per id_produksi — lihat penjelasan di stitchBatchWithOutflow() kenapa
     * seeding dari $outflowData (yang sudah difilter per-lahan) DIHAPUS. Ini
     * adalah fix untuk bug "jumlah pegawai beda antara tampilan & export".
     *
     * Struktur: [id_produksi => Collection<DetailHasilPaletRotary>]
     */
    private array $detailByProduksiCache = [];

    /**
     * OPTIMASI #5: cache SEMUA baris BahanPenolongRotary per id_produksi.
     * Sama seperti detailByProduksiCache: HANYA boleh diisi dari query
     * whereIn(id_produksi) yang lengkap (bukan hasil filter per-lahan),
     * supaya konsisten dengan pola caching lain di service ini.
     *
     * Bahan penolong DITAMPILKAN sebagai info tambahan saja di outflow_detail
     * (dialokasikan proporsional berdasarkan persentase kubikasi baris, sama
     * seperti pola pekerja/penyusutan) — TIDAK ikut memengaruhi perhitungan
     * harga_v_ongkos / harga_vop.
     *
     * Relasi 'bahanPenolong' (ke BahanPenolongProduksi) di-eager-load SEKALIAN
     * kolom harga/satuan supaya subtotal bisa dihitung tanpa query tambahan
     * per baris.
     *
     * Struktur: [id_produksi => Collection<BahanPenolongRotary>]
     */
    private array $bahanPenolongByProduksiCache = [];

    public function getLaporanBatch($month = null, $year = null, $nama_lahan = 'Semua Lahan', $perPage = 10)
    {
        $query = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu',
        ])
            ->where('jumlah_batang', '>', 0);

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

        $laporanFinal = $this->mergeZeroInflowBatches($laporanFinal, true);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $itemCollection = collect($laporanFinal);
        $slice = $itemCollection->slice(($currentPage - 1) * $perPage, $perPage)->values()->all();

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

        $laporanFinal = $this->mergeZeroInflowBatches($laporanFinal, false);

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

        return collect($laporanFinal);
    }

    private function buildLaporanItemForClosure(PenggunaanLahanRotary $closure): array
    {
        $lastClosure = $this->getClosuresForLahan($closure->id_lahan)
            ->filter(fn ($r) => $r->created_at->lt($closure->created_at))
            ->last();

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
            ->values();

        $tempGroup = [];
        foreach ($batchRecords as $record) {
            $tempGroup[] = $record;
            if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                array_pop($tempGroup);
                break;
            }
        }

        $tempGroup = array_reverse($tempGroup);
        $batch = $this->stitchBatchWithOutflow($tempGroup);

        $end = $closure->created_at;
        [$start, $notaIds] = $this->calculateInflowBoundaries($closure, $lastClosure);

        $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status'], $closure->id_jenis_kayu, $notaIds);

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
        // FIX (unit consistency bug): harga_vopb = harga_vop (rupiah PER M3) ditambah
        // bahan penolong yang JUGA sudah dikonversi ke rupiah per m3. Sebelumnya
        // grand_total_outflow_bahan_penolong (nominal rupiah TOTAL/flat, mis. 420.000)
        // dijumlahkan langsung ke harga_v_ongkos_penyusutan (rupiah/m3, mis. 2.469.674)
        // tanpa dibagi kubikasi dulu — dua satuan berbeda dijumlahkan mentah-mentah.
        // Efeknya makin parah saat diakumulasi lintas banyak batch di
        // getSummaryLaporanLahan() (bisa meledak jadi puluhan juta). Sekarang bahan
        // penolong dibagi grand_total_outflow_m3 dulu supaya benar-benar "rupiah/m3",
        // baru dijumlahkan ke harga_vop — konsisten dengan cara kolom "Rp x / m3" di
        // level baris/grup outflow dihitung di Blade (lihat $bahanPerM3Baris,
        // $bahanPenolongPerM3).
        $hargaBahanPenolongPerM3 = $batch['grand_total_outflow_m3'] > 0
            ? ($batch['grand_total_outflow_bahan_penolong'] / $batch['grand_total_outflow_m3'])
            : 0.0;
        $harga_v_ongkos_penyusutan_bahan = $harga_v_ongkos_penyusutan + $hargaBahanPenolongPerM3;

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
                'harga_vopb' => $harga_v_ongkos_penyusutan_bahan,
                // Dipakai di Blade untuk menyembunyikan kolom "Veneer+Ongkos+Susut+Bahan Penolong"
                // kalau batch ini memang tidak punya bahan penolong sama sekali.
                'total_bahan_penolong' => $batch['grand_total_outflow_bahan_penolong'],
                // === RAW TOTALS (bukan rasio) ===
                // Disimpan mentah supaya getSummaryLaporanLahan() bisa menghitung harga
                // gabungan lintas-batch dari TOTAL (Σkomponen / Σm3), BUKAN dari
                // avg() rasio per-batch. avg() dari rasio per-batch salah kalau volume
                // m3 antar batch tidak sama besar (bias ke batch bervolume kecil).
                'total_poin_raw' => (float) $dataMasuk->sum('poin'),
                'total_ongkos_pkj' => (float) $batch['grand_total_outflow_ongkos_pkj'],
                'total_penyusutan' => (float) $batch['grand_total_outflow_penyusutan'],
            ],
        ];
    }

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

    private function getRecordsForLahanJenis($idLahan, $idJenisKayu)
    {
        $key = $idLahan.'-'.$idJenisKayu;

        if (! array_key_exists($key, $this->recordsByLahanJenisCache)) {
            $records = PenggunaanLahanRotary::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->orderBy('created_at', 'desc')
                ->get();

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

        // === RAW TOTALS (Σ dari semua batch) ===
        // Semua harga gabungan (harga_veneer, harga_v_ongkos, harga_vop, harga_vopb)
        // WAJIB dihitung dari total mentah dibagi total m3 keluar (weighted by
        // volume) — pola yang sama persis dengan mergeZeroInflowBatches() saat
        // menggabungkan batch. TIDAK BOLEH pakai avg() dari rasio per-batch, karena
        // itu menganggap tiap batch berbobot sama padahal volumenya beda-beda —
        // hasilnya bias/"halu" dibanding angka riil.
        $totalPoin = $laporanFinalCollection->sum('summary.total_poin_raw');
        $totalOngkos = $laporanFinalCollection->sum('summary.total_ongkos_pkj');
        $totalPenyusutan = $laporanFinalCollection->sum('summary.total_penyusutan');
        $totalBahanPenolong = $laporanFinalCollection->sum('summary.total_bahan_penolong');

        $totalHargaVop = $totalKeluarM3 > 0
            ? (float) (($totalPoin + $totalOngkos + $totalPenyusutan) / $totalKeluarM3)
            : 0.0;

        // FIX (unit consistency bug): total_bahan_penolong di sini adalah AKUMULASI
        // NOMINAL RUPIAH dari SEMUA batch dalam $laporanFinalCollection (bisa
        // puluhan batch dalam sebulan, tiap batch nyumbang ratusan ribu → total bisa
        // puluhan juta). Sebelumnya nominal akumulasi ini dijumlahkan mentah-mentah
        // ke total_harga_vop (yang satuannya rupiah PER M3), menghasilkan angka yang
        // tidak masuk akal (mis. Rp 30 juta / m3). Sekarang dibagi totalKeluarM3
        // dulu (weighted by volume, sama seperti pola total_harga_vop di atas)
        // supaya satuannya konsisten sebelum dijumlahkan.
        $hargaBahanPenolongPerM3 = $totalKeluarM3 > 0
            ? ($totalBahanPenolong / $totalKeluarM3)
            : 0.0;

        return [
            'total_kayu_masuk' => $laporanFinalCollection->sum('summary.total_kayu_masuk') ?? 0,
            'total_kubikasi_kayu_masuk' => $totalMasukM3 ?? 0,
            'total_poin_masuk' => $totalPoin ?? 0,
            'total_kubikasi_veneer' => $totalKeluarM3 ?? 0,
            'rata_rata_rendemen' => $totalMasukM3 > 0
                ? number_format(($totalKeluarM3 / $totalMasukM3) * 100, 2).'%'
                : '0%',
            'total_harga_veneer' => $totalKeluarM3 > 0
                ? (float) ($totalPoin / $totalKeluarM3)
                : 0.0,
            'total_harga_v_ongkos' => $totalKeluarM3 > 0
                ? (float) (($totalPoin + $totalOngkos) / $totalKeluarM3)
                : 0.0,
            'total_harga_vop' => $totalHargaVop,
            // total_bahan_penolong ditambahkan sebagai NOMINAL PER-M3 (sudah dibagi
            // totalKeluarM3 di atas), konsisten dengan buildLaporanItemForClosure()
            // & mergeZeroInflowBatches() yang juga membagi dengan kubikasi keluar
            // sebelum menjumlahkan ke harga_vop.
            'total_harga_vopb' => $totalHargaVop + $hargaBahanPenolongPerM3,
        ];
    }

    private function stitchBatchWithOutflow(array $tempGroup): array
    {
        $records = collect($tempGroup);
        $first = $records->first();
        $last = $records->first(fn ($i) => $i->jumlah_batang > 0);

        $ongkosPekerja = $this->cachedSingle('harga_pegawai', function () {
            return HargaPegawai::first()->value('harga') ?? 0;
        });

        $idsPenggunaanLahan = $records->pluck('id')->toArray();

        $outflowData = DetailHasilPaletRotary::with([
            'produksi:id,tgl_produksi,id_mesin',
            'produksi.detailPegawaiRotary:id,id_produksi',
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

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

        // === FIX BUG "JUMLAH PEKERJA BEDA ANTARA PREVIEW & EXPORT" ===
        // detailByProduksiCache HANYA boleh diisi dari query LENGKAP (semua lahan)
        // per id_produksi di bawah ini. JANGAN seed dari $outflowData (yang sudah
        // terpotong per id_penggunaan_lahan/lahan ini saja).
        $missingProduksiIds = array_values(array_filter(
            $produksiIds,
            fn ($id) => ! isset($this->detailByProduksiCache[$id])
        ));

        if (! empty($missingProduksiIds)) {
            $fetched = DetailHasilPaletRotary::whereIn('id_produksi', $missingProduksiIds)
                ->get()
                ->each(function ($d) use ($ukuranFk) {
                    $d->setRelation(
                        'setoranPaletUkuran',
                        $this->cached(Ukuran::class, $d->{$ukuranFk}, ['id', 'panjang', 'lebar', 'tebal'])
                    );
                })
                ->groupBy('id_produksi');

            foreach ($fetched as $prodId => $rows) {
                $this->detailByProduksiCache[$prodId] = $rows;
            }
        }

        foreach ($produksiIds as $prodId) {
            $rows = $this->detailByProduksiCache[$prodId] ?? collect();
            foreach ($rows as $d) {
                if (! $d->relationLoaded('setoranPaletUkuran')) {
                    $d->setRelation(
                        'setoranPaletUkuran',
                        $this->cached(Ukuran::class, $d->{$ukuranFk}, ['id', 'panjang', 'lebar', 'tebal'])
                    );
                }
            }
        }

        // === OPTIMASI #5: seed bahan_penolong_rotary per id_produksi ===
        // Sama seperti detailByProduksiCache di atas: HANYA diisi dari query
        // whereIn(id_produksi) yang LENGKAP, bukan dari $outflowData yang sudah
        // difilter per-lahan. Ini murni info tambahan (tidak dipakai untuk
        // hitung harga_v_ongkos/harga_vop), tapi tetap ikut pola caching yang
        // sama biar konsisten dan tidak query berulang untuk id_produksi yang
        // sama.
        $missingBahanIds = array_values(array_filter(
            $produksiIds,
            fn ($id) => ! isset($this->bahanPenolongByProduksiCache[$id])
        ));

        if (! empty($missingBahanIds)) {
            $fetchedBahan = BahanPenolongRotary::with([
                'bahanPenolong:id,nama_bahan_penolong,satuan,harga',
            ])
                ->whereIn('id_produksi', $missingBahanIds)
                ->get()
                ->groupBy('id_produksi');

            foreach ($fetchedBahan as $prodId => $rows) {
                $this->bahanPenolongByProduksiCache[$prodId] = $rows;
            }

            // Pastikan id_produksi tanpa bahan penolong tetap tercatat sebagai
            // collection kosong, supaya tidak query ulang di kemudian hari.
            foreach ($missingBahanIds as $prodId) {
                if (! isset($this->bahanPenolongByProduksiCache[$prodId])) {
                    $this->bahanPenolongByProduksiCache[$prodId] = collect();
                }
            }
        }

        $totalOutputHarian = collect($produksiIds)
            ->mapWithKeys(function ($prodId) {
                $details = $this->detailByProduksiCache[$prodId] ?? collect();
                $sum = $details->sum(function ($d) {
                    $u = $d->setoranPaletUkuran;

                    return $u ? ($u->panjang * $u->lebar * $u->tebal * $d->total_lembar) / 10_000_000 : 0;
                });

                return [$prodId => $sum];
            });

        $groupedOutflow = $outflowData->map(function ($hasil) use ($totalOutputHarian) {
            $produksi = $hasil->produksi;
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) ($hasil->total_lembar ?? 0);

            $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10_000_000 : 0;
            $m3TotalAllLahan = isset($totalOutputHarian[$hasil->id_produksi]) ? (float) $totalOutputHarian[$hasil->id_produksi] : 0.0;
            $pekerja = $produksi ? ($produksi->detailPegawaiRotary ? $produksi->detailPegawaiRotary->count() : 0) : 0;

            // Persentase kontribusi kubikasi baris ini terhadap total output produksi
            // (mesin yang sama) hari itu. Dipakai untuk PEKERJA & PENYUSUTAN sekaligus,
            // dan sekarang juga untuk BAHAN PENOLONG (info tambahan, bukan biaya).
            $persentaseKubikasi = ($m3TotalAllLahan > 0) ? ($m3 / $m3TotalAllLahan) : 0.0;

            // Pekerja: simpan msa MENTAH (float) per baris — TIDAK dibulatkan di sini.
            // round() + max(1,...) baru diterapkan SEKALI nanti di level grup.
            $msa = $pekerja * $persentaseKubikasi;

            // Penyusutan: pakai pola YANG SAMA PERSIS seperti pekerja — simpan
            // porsi mentah (penyusutan mesin dikali persentase kubikasi baris ini),
            // JANGAN dijumlah/diambil flat di sini. Agregasi (sum) baru dilakukan
            // sekali di level grup, supaya konsisten dan tidak dobel-hitung kalau
            // 1 grup berisi beberapa baris detail kecil dari id_produksi yang sama.
            $penyusutanMesin = ($produksi && $produksi->mesin) ? ($produksi->mesin->penyusutan ?? 0) : 0;
            $penyusutanPorsi = $penyusutanMesin * $persentaseKubikasi;

            // Bahan penolong: pola SAMA PERSIS seperti penyusutan — simpan porsi
            // mentah (jumlah bahan x persentase kubikasi baris ini) per baris,
            // per nama_bahan, LENGKAP dengan harga_satuan dari master
            // BahanPenolongProduksi. Di-sum sekali di level grup nanti. INFO
            // TAMBAHAN saja — TIDAK ikut ke grand_total_outflow_ongkos_pkj/
            // penyusutan, jadi harga_v_ongkos & harga_vop tidak terpengaruh
            // sama sekali.
            //
            // HARDCODE: bahan penolong di lahan rotary ini SELALU "Solasi"
            // (satu-satunya jenis bahan penolong yang dipakai di sini), jadi
            // nama_bahan tidak lagi diambil dari kolom nama_bahan pivot atau
            // dari master bahan_penolong — langsung dipaksa jadi "Solasi"
            // supaya label di laporan konsisten, apa pun yang tercatat di DB.
            $bahanRows = $this->bahanPenolongByProduksiCache[$hasil->id_produksi] ?? collect();
            $bahanPorsi = $bahanRows->map(function ($b) use ($persentaseKubikasi) {
                $master = $b->bahanPenolong;

                $namaBahan = 'Solasi';

                $satuan = $master->satuan ?? '';
                $hargaSatuan = (float) ($master->harga ?? 0);

                return [
                    'nama_bahan' => $namaBahan,
                    'satuan' => $satuan,
                    'jumlah_porsi' => $b->jumlah * $persentaseKubikasi,
                    'harga_satuan' => $hargaSatuan,
                ];
            })->values();

            return [
                'tgl' => $produksi ? Carbon::parse($produksi->tgl_produksi)->format('d-m-Y') : ($hasil->created_at ? Carbon::parse($hasil->created_at)->format('d-m-Y') : '-'),
                'mesin' => $produksi ? ($produksi->mesin ? ($produksi->mesin->nama_mesin ?? 'Unknown') : 'Unknown') : 'Unknown',
                'jam_kerja' => '06:00 - 16:00',
                'ukuran' => $ukuran ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'msa' => $msa, // raw, belum dibulatkan
                'penyusutan_porsi' => $penyusutanPorsi, // raw, belum di-sum level grup
                'bahan_penolong_porsi' => $bahanPorsi, // raw, belum di-sum level grup (info saja)
                'panjang' => $ukuran->panjang,
                'lebar' => $ukuran->lebar,
                'tebal' => $ukuran->tebal,
            ];
        })->groupBy(fn ($item) => $item['tgl'].$item['mesin'].$item['ukuran'])
            ->map(function ($group) use ($ongkosPekerja) {
                $totalMsa = $group->sum('msa');
                $totalPekerja = max(1, round($totalMsa));

                // Penyusutan grup = jumlah seluruh porsi mentah dari baris-baris dalam
                // grup ini (bukan flat/ambil salah satu baris saja), konsisten dengan
                // cara pekerja diagregasi di atas.
                $totalPenyusutan = $group->sum('penyusutan_porsi');

                // Bahan penolong grup = gabungkan seluruh porsi mentah dari baris-baris
                // dalam grup ini, lalu sum per nama_bahan (yang sekarang selalu
                // "Solasi"). harga_satuan diambil dari master (sama untuk semua baris
                // dengan nama_bahan yang sama), dan subtotal = jumlah x harga_satuan.
                // Murni ditampilkan sebagai info tambahan, TIDAK memengaruhi
                // harga_v_ongkos / harga_vop.
                $bahanPenolong = $group
                    ->pluck('bahan_penolong_porsi')
                    ->flatten(1)
                    ->groupBy('nama_bahan')
                    ->map(function ($items, $nama) {
                        // FIX: jumlah dibulatkan ke bilangan bulat NORMAL (bukan 2 desimal
                        // lagi), dan subtotal WAJIB dihitung dari jumlah yang SUDAH
                        // dibulatkan ini — bukan dari jumlah_porsi desimal asli. Ini supaya
                        // subtotal (dan turunannya: total_bahan_penolong, harga_vopb, Harga
                        // Total/m³) SELALU konsisten & bisa di-cross-check manual dengan
                        // angka Solasi yang tampil di layar/export.
                        $jumlah = round($items->sum('jumlah_porsi'));
                        $hargaSatuan = (float) ($items->first()['harga_satuan'] ?? 0);
                        $satuan = $items->first()['satuan'] ?? '';

                        return [
                            'nama_bahan' => $nama,
                            'jumlah' => $jumlah,
                            'satuan' => $satuan,
                            'harga_satuan' => $hargaSatuan,
                            'subtotal' => $jumlah * $hargaSatuan,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'tgl' => $group[0]['tgl'],
                    'mesin' => $group[0]['mesin'],
                    'jam_kerja' => $group[0]['jam_kerja'],
                    'ukuran' => $group[0]['ukuran'],
                    'total_banyak' => $group->sum('banyak'),
                    'total_kubikasi' => number_format($group->sum('kubikasi'), 4),
                    'pekerja' => $totalPekerja.' Orang',
                    'ongkos' => $totalPekerja * $ongkosPekerja,
                    'penyusutan' => $totalPenyusutan,
                    'bahan_penolong' => $bahanPenolong, // info tambahan, TIDAK dipakai di harga_v_ongkos/harga_vop
                    'total_bahan_penolong' => collect($bahanPenolong)->sum('subtotal'),
                    'panjang' => $group[0]['panjang'],
                    'lebar' => $group[0]['lebar'],
                    'tebal' => $group[0]['tebal'],
                ];
            })->values()->toArray();

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'grand_total_outflow_m3' => collect($groupedOutflow)->sum('total_kubikasi'),
            'grand_total_outflow_ongkos_pkj' => collect($groupedOutflow)->sum('ongkos'),
            'grand_total_outflow_penyusutan' => collect($groupedOutflow)->sum('penyusutan'),
            'grand_total_outflow_bahan_penolong' => collect($groupedOutflow)->sum('total_bahan_penolong'),
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

            $allDetails = DetailTurusanKayu::with(['jenisKayu:id,nama_kayu', 'lahan:id,kode_lahan'])
                ->where('lahan_id', $idLahan)
                ->where('jenis_kayu_id', $idJenisKayu)
                ->whereIn('id_kayu_masuk', $kayuMasukIds)
                ->get()
                ->groupBy('id_kayu_masuk');

            $rentangCache = [];
            $getRentangList = function ($jenisKayuId, $grade, $panjang) use (&$rentangCache) {
                $key = $jenisKayuId.'-'.$grade.'-'.$panjang;
                if (! array_key_exists($key, $rentangCache)) {
                    $rentangCache[$key] = HargaKayu::where('id_jenis_kayu', $jenisKayuId)
                        ->where('grade', $grade)
                        ->where('panjang', $panjang)
                        ->orderBy('diameter_terkecil')
                        ->get();
                }

                return $rentangCache[$key];
            };

            $notaInflows = $notas->map(function ($nota) use ($allDetails, $getRentangList) {
                $details = $allDetails->get($nota->id_kayu_masuk, collect());

                $totalQty = (int) $details->sum('kuantitas');

                $hargaKosongCount = $details->filter(fn ($item) => empty($item->harga))->count();

                $totalPoin = 0;
                $totalKubikasiAkumulasi = 0.0;

                foreach ($details->groupBy(function ($item) {
                    $kodeLahan = optional($item->lahan)->kode_lahan ?? '-';
                    $idJenisKayuResolved = optional($item->jenisKayu)->id ?? ($item->id_jenis_kayu ?? null);

                    return $kodeLahan.'|'.$item->grade.'|'.$item->panjang.'|'.$idJenisKayuResolved;
                }) as $subGroup) {
                    $first = $subGroup->first();
                    $idJenisKayuGrup = optional($first->jenisKayu)->id ?? ($first->id_jenis_kayu ?? null);
                    $rentangList = $getRentangList($idJenisKayuGrup, $first->grade, $first->panjang);

                    $terpakaiIds = collect();

                    foreach ($rentangList as $rentang) {
                        $kelompok = $subGroup->filter(fn ($item) => $item->diameter >= $rentang->diameter_terkecil
                            && $item->diameter <= $rentang->diameter_terbesar);

                        if ($kelompok->isNotEmpty()) {
                            $harga = $kelompok->first()->harga ?? 0;
                            $kubikasiGrup = $kelompok->sum(fn ($item) => $item->kubikasi);

                            $totalPoin += round($harga * $kubikasiGrup * 1000);
                            $totalKubikasiAkumulasi += round($kubikasiGrup, 4);

                            $terpakaiIds = $terpakaiIds->merge($kelompok->pluck('id'));
                        }
                    }

                    $sisa = $subGroup->whereNotIn('id', $terpakaiIds);
                    foreach ($sisa as $item) {
                        $harga = $item->harga ?? 0;
                        $totalPoin += round($harga * $item->kubikasi * 1000);
                        $totalKubikasiAkumulasi += round($item->kubikasi, 4);
                    }
                }

                $totalKubikasi = (float) round($totalKubikasiAkumulasi, 4);

                return [
                    'tanggal' => $nota->created_at->format('d-m-Y'),
                    'seri' => ($hargaKosongCount > 0)
                        ? $nota->kayuMasuk->seri." ⚠️ (Harga Belum Atur: $hargaKosongCount Baris)"
                        : $nota->kayuMasuk->seri,
                    'banyak' => $totalQty,
                    'kubikasi' => $totalKubikasi,
                    'poin' => (float) $totalPoin,
                ];
            })->filter(fn ($x) => $x['banyak'] > 0)->values();
        }

        $opnameCacheKey = 'opname_'.$idLahan.'-'.$idJenisKayu.'-'.$batasAtas.'-'.($start ?? 'null');

        $opnames = $this->cachedSingle($opnameCacheKey, function () use ($idLahan, $idJenisKayu, $batasAtas, $start) {
            $opnameQuery = HppAverageLog::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->where('keterangan', 'like', 'STOK OPNAME%')
                ->where('created_at', '<=', $batasAtas);

            if ($start) {
                $opnameQuery->where('created_at', '>', $start);
            }

            return $opnameQuery->get();
        });

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
        usort($laporanFinal, function ($a, $b) {
            $tglA = $this->parseTglFlexible($a['batch_info']['tgl_buka_lahan']);
            $tglB = $this->parseTglFlexible($b['batch_info']['tgl_buka_lahan']);

            return $tglA <=> $tglB;
        });

        $mergedList = [];

        foreach ($laporanFinal as $item) {
            $totalMasuk = (float) $item['summary']['total_masuk_m3'];
            $totalKeluar = (float) $item['summary']['total_keluar_m3'];

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

                    $parent['outflow'] = array_merge($parent['outflow'], $item['outflow']);

                    $totalOutflowM3 = collect($parent['outflow'])->sum(fn ($x) => (float) str_replace(',', '', $x['total_kubikasi']));
                    $totalOngkos = collect($parent['outflow'])->sum('ongkos');
                    $totalPenyusutan = collect($parent['outflow'])->sum('penyusutan');
                    $totalBahanPenolong = collect($parent['outflow'])->sum('total_bahan_penolong');

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

                    // FIX (unit consistency bug): sama seperti di buildLaporanItemForClosure()
                    // & getSummaryLaporanLahan() — totalBahanPenolong di sini adalah nominal
                    // rupiah gabungan hasil merge beberapa baris outflow (bisa lebih dari satu
                    // batch zero-inflow yang digabung ke parent yang sama), jadi WAJIB dibagi
                    // totalOutflowM3 dulu (jadi rupiah/m3) sebelum ditambahkan ke harga_vop,
                    // bukan dijumlahkan sebagai nominal rupiah total langsung seperti sebelumnya.
                    $hargaBahanPenolongPerM3 = $totalOutflowM3 > 0
                        ? ($totalBahanPenolong / $totalOutflowM3)
                        : 0.0;
                    $parent['summary']['harga_vopb'] = $parent['summary']['harga_vop'] + $hargaBahanPenolongPerM3;

                    $parent['summary']['total_bahan_penolong'] = $totalBahanPenolong;

                    // Jaga raw totals tetap sinkron setelah merge, supaya
                    // getSummaryLaporanLahan() tetap akurat kalau dipanggil
                    // dengan collection hasil merge ini.
                    $parent['summary']['total_poin_raw'] = $totalPoinVal;
                    $parent['summary']['total_ongkos_pkj'] = $totalOngkos;
                    $parent['summary']['total_penyusutan'] = $totalPenyusutan;

                    $parent['batch_info']['tgl_tutup_lahan'] = $item['batch_info']['tgl_tutup_lahan'];
                    $parent['batch_info']['jumlah_batang_akhir'] = $item['batch_info']['jumlah_batang_akhir'];
                    $parent['batch_info']['status'] = $item['batch_info']['status'];

                    continue;
                }
            }

            $mergedList[] = $item;
        }

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

        $currentClosureLog = $this->cachedSingle(
            'hpp_ref_'.$closure->id,
            fn () => HppAverageLog::where('referensi_type', PenggunaanLahanRotary::class)
                ->where('referensi_id', $closure->id)
                ->first()
        );

        if ($currentClosureLog) {
            $lastClosureLogKey = 'hpp_keluar_'.$closure->id_lahan.'-'.$closure->id_jenis_kayu.'-'.$currentClosureLog->created_at;
            $lastClosureLog = $this->cachedSingle(
                $lastClosureLogKey,
                fn () => HppAverageLog::where('id_lahan', $closure->id_lahan)
                    ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                    ->where('tipe_transaksi', 'keluar')
                    ->where('created_at', '<', $currentClosureLog->created_at)
                    ->orderBy('created_at', 'desc')
                    ->first()
            );

            if ($lastClosureLog) {
                $prevClosure = $this->cached(PenggunaanLahanRotary::class, $lastClosureLog->referensi_id);
                if ($prevClosure) {
                    $start = $prevClosure->created_at;
                }
            } else {
                $masukLogsKey = 'hpp_masuk_'.$closure->id_lahan.'-'.$closure->id_jenis_kayu.'-'.$currentClosureLog->created_at;
                $notaIds = $this->cachedSingle($masukLogsKey, function () use ($closure, $currentClosureLog) {
                    return HppAverageLog::where('id_lahan', $closure->id_lahan)
                        ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                        ->where('tipe_transaksi', 'masuk')
                        ->where('referensi_type', NotaKayu::class)
                        ->where('created_at', '<', $currentClosureLog->created_at)
                        ->pluck('referensi_id')
                        ->toArray();
                });

                $trackedQty = 0;
                if (! empty($notaIds)) {
                    $trackedQtyKey = 'tracked_qty_'.$closure->id_lahan.'-'.$closure->id_jenis_kayu.'-'.md5(implode(',', $notaIds));
                    $trackedQty = $this->cachedSingle($trackedQtyKey, function () use ($closure, $notaIds) {
                        return DetailTurusanKayu::where('lahan_id', $closure->id_lahan)
                            ->where('jenis_kayu_id', $closure->id_jenis_kayu)
                            ->whereIn('id_kayu_masuk', function ($q) use ($notaIds) {
                                $q->select('id_kayu_masuk')->from('nota_kayus')->whereIn('id', $notaIds);
                            })
                            ->sum('kuantitas');
                    });
                }

                $untrackedQty = $currentClosureLog->total_batang - $trackedQty;

                if ($untrackedQty > 0) {
                    $minNotaCreatedAt = null;
                    if (! empty($notaIds)) {
                        $minNotaKey = 'min_nota_'.md5(implode(',', $notaIds));
                        $minNotaCreatedAt = $this->cachedSingle(
                            $minNotaKey,
                            fn () => NotaKayu::whereIn('id', $notaIds)->min('created_at')
                        );
                    }
                    $firstHppTime = $minNotaCreatedAt ?: $currentClosureLog->created_at;

                    $preHppKey = 'pre_hpp_notas_'.$closure->id_lahan.'-'.$closure->id_jenis_kayu.'-'.$firstHppTime;
                    $preHppNotas = $this->cachedSingle($preHppKey, function () use ($closure, $firstHppTime) {
                        return NotaKayu::where('status', 'like', '%Sudah Diperiksa%')
                            ->where('created_at', '<', $firstHppTime)
                            ->whereHas('kayuMasuk.detailTurusanKayus', function ($q) use ($closure) {
                                $q->where('lahan_id', $closure->id_lahan)
                                    ->where('jenis_kayu_id', $closure->id_jenis_kayu);
                            })
                            ->orderBy('created_at', 'desc')
                            ->get();
                    });

                    $accumulated = 0;
                    $preHppNotaIds = [];
                    foreach ($preHppNotas as $n) {
                        if ($accumulated >= $untrackedQty) {
                            break;
                        }
                        $qtyKey = 'qty_kayu_masuk_'.$n->id_kayu_masuk.'-'.$closure->id_lahan.'-'.$closure->id_jenis_kayu;
                        $qty = $this->cachedSingle($qtyKey, function () use ($n, $closure) {
                            return DetailTurusanKayu::where('id_kayu_masuk', $n->id_kayu_masuk)
                                ->where('lahan_id', $closure->id_lahan)
                                ->where('jenis_kayu_id', $closure->id_jenis_kayu)
                                ->sum('kuantitas');
                        });

                        $preHppNotaIds[] = $n->id;
                        $accumulated += $qty;
                    }

                    $notaIds = array_merge($notaIds, $preHppNotaIds);
                }

                if (! empty($notaIds)) {
                    $minNotaFinalKey = 'min_nota_final_'.md5(implode(',', $notaIds));
                    $minNotaCreatedAt = $this->cachedSingle(
                        $minNotaFinalKey,
                        fn () => NotaKayu::whereIn('id', $notaIds)->min('created_at')
                    );
                    if ($minNotaCreatedAt) {
                        $start = Carbon::parse($minNotaCreatedAt)->subDays(30);
                    }
                }
            }
        }

        return [$start, $notaIds];
    }
}
