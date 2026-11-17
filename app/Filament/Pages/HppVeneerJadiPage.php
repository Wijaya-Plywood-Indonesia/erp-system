<?php

namespace App\Filament\Pages;

use App\Concerns\FlexibleLogSearch;
use App\Models\HppVeneerJadiLog;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Services\HppVeneerJadiService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Livewire\WithPagination;
use UnitEnum;

/**
 * Log HPP Veneer Jadi — tampilan & hitungan sengaja dibuat sama dengan HppVeneerBasahPage:
 *   - satu baris = satu kejadian (masuk/keluar)
 *   - Komponen HPP/m³ : K kayu · P pekerja · M mesin · B bahan penolong  (+ L bila ada bagian tanpa rincian)
 *   - HPP Average     : rata-rata STOK setelah transaksi (Nilai Stok ÷ Stok Kubikasi)
 *   - kotak HPP bulanan (rata-rata tertimbang m³ dari pemasukan bulan itu)
 *   - label asal: lahan, mesin kering (Dryer/Kedi), jalur (kering langsung / repair / joint), estimasi
 */
class HppVeneerJadiPage extends Page
{
    use FlexibleLogSearch;
    use HasPageShield;
    use WithPagination;

    protected string $view = 'filament.pages.hpp-veneer-jadi-page';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Log HPP Veneer Jadi';

    protected static string|UnitEnum|null $navigationGroup = 'Log';

    protected static ?string $title = 'Log HPP Veneer Jadi';

    protected static ?int $navigationSort = 13;

    public const JALUR = [
        'kering_langsung' => 'Kering langsung',
        'repair'          => 'Repair',
        'joint'           => 'Joint',
    ];

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang = '';
    public string $filterLebar = '';
    public string $filterTebal = '';
    public string $filterKw = '';
    public string $filterJalur = '';
    public string $filterBulan = '';          // YYYY-MM, kosong = semua bulan
    public string $filterTanggalMulai = '';
    public string $filterTanggalSelesai = '';
    public bool $tampilNilaiStok = false;     // kolom Nilai Stok disembunyikan secara bawaan

    /** Total semua komponen per m³ (sama seperti halaman basah, ditambah bagian tanpa rincian). */
    private const KOMP = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0)+COALESCE(hpp_lainnya,0))';

    // ── Query dasar ────────────────────────────────────────────
    protected function queryLogs(bool $denganBulan = true)
    {
        return HppVeneerJadiLog::query()
            ->when($denganBulan && preg_match('/^\d{4}-\d{2}$/', $this->filterBulan), function ($q) {
                $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $this->filterBulan.'-01')->startOfMonth();
                $q->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()]);
            })
            ->when($this->filterTanggalMulai, fn ($q) => $q->whereDate('tanggal', '>=', $this->filterTanggalMulai))
            ->when($this->filterTanggalSelesai, fn ($q) => $q->whereDate('tanggal', '<=', $this->filterTanggalSelesai))
            ->when($this->filterJenisKayu, fn ($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang, fn ($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterLebar, fn ($q) => $q->where('lebar', $this->filterLebar))
            ->when($this->filterTebal, fn ($q) => $q->where('tebal', $this->filterTebal))
            ->when($this->filterJalur, fn ($q) => $q->where('jalur', $this->filterJalur))
            ->when(trim($this->filterKw) !== '', fn ($q) => $this->flexibleSearch($q, $this->filterKw, $this->logSearchConfig()));
    }

    // ── Log transaksi (50 baris per halaman) ───────────────────
    public function getLogsProperty()
    {
        return $this->queryLogs()
            ->with('jenisKayu')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(50);
    }

    // ── Ringkasan SELURUH hasil filter (dihitung di DB) ────────
    public function getRingkasanProperty(): array
    {
        $agg = $this->queryLogs()
            ->reorder()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'masuk'  THEN total_lembar   ELSE 0 END), 0) AS masuk_lbr,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'keluar' THEN total_lembar   ELSE 0 END), 0) AS keluar_lbr,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'masuk'  THEN total_kubikasi ELSE 0 END), 0) AS masuk_m3,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'keluar' THEN total_kubikasi ELSE 0 END), 0) AS keluar_m3,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'masuk'  THEN nilai_stok     ELSE 0 END), 0) AS masuk_nilai,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'keluar' THEN nilai_stok     ELSE 0 END), 0) AS keluar_nilai
            ")
            ->first();

        $last = $this->queryLogs()->orderByDesc('tanggal')->orderByDesc('id')->first();

        return [
            'masuk_lbr'    => (float) $agg->masuk_lbr,
            'keluar_lbr'   => (float) $agg->keluar_lbr,
            'masuk_m3'     => (float) $agg->masuk_m3,
            'keluar_m3'    => (float) $agg->keluar_m3,
            'masuk_nilai'  => (float) $agg->masuk_nilai,
            'keluar_nilai' => (float) $agg->keluar_nilai,
            'last'         => $last,
        ];
    }

    // ── Kembali ke halaman 1 setiap filter berubah ─────────────
    public function updatedFilterJenisKayu(): void { $this->resetPage(); }
    public function updatedFilterPanjang(): void { $this->resetPage(); }
    public function updatedFilterLebar(): void { $this->resetPage(); }
    public function updatedFilterTebal(): void { $this->resetPage(); }
    public function updatedFilterKw(): void { $this->resetPage(); }
    public function updatedFilterJalur(): void { $this->resetPage(); }
    public function updatedFilterBulan(): void { $this->resetPage(); }

    public function getBulanListProperty()
    {
        return HppVeneerJadiLog::query()
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m') AS bln")
            ->distinct()
            ->orderByDesc('bln')
            ->pluck('bln');
    }

    public function getUkuranListProperty()
    {
        return HppVeneerJadiLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }

    public function resetFilter(): void
    {
        $this->filterJenisKayu = '';
        $this->filterPanjang = '';
        $this->filterLebar = '';
        $this->filterTebal = '';
        $this->filterKw = '';
        $this->filterJalur = '';
        $this->filterBulan = '';
        $this->filterTanggalMulai = '';
        $this->filterTanggalSelesai = '';
        $this->resetPage();
    }

    /** Klik baris rekap → pilih jenis kayu + ukuran tersebut di filter. */
    public function pilihUkuran(string $jenis, string $panjang, string $lebar, string $tebal): void
    {
        $this->filterJenisKayu = $jenis;
        $this->filterPanjang = $panjang;
        $this->filterLebar = $lebar;
        $this->filterTebal = $tebal;
        $this->resetPage();
    }

    // ── HPP bulanan ────────────────────────────────────────────

    /** Log MASUK yang sudah punya komponen HPP (dasar semua hitungan HPP bulanan). */
    protected function queryHppMasuk(bool $denganBulan)
    {
        return $this->queryLogs($denganBulan)
            ->where('tipe_transaksi', 'masuk')
            ->whereRaw(self::KOMP.' > 0');
    }

    protected function selectHpp(): string
    {
        $komp = self::KOMP;

        return "
            DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
            COUNT(*)                                             AS transaksi,
            SUM(total_kubikasi)                                  AS m3,
            SUM(COALESCE(hpp_kayu,0)           * total_kubikasi) AS n_kayu,
            SUM(COALESCE(hpp_pekerja,0)        * total_kubikasi) AS n_pekerja,
            SUM(COALESCE(hpp_mesin,0)          * total_kubikasi) AS n_mesin,
            SUM(COALESCE(hpp_bahan_penolong,0) * total_kubikasi) AS n_bahan,
            SUM(COALESCE(hpp_lainnya,0)        * total_kubikasi) AS n_lainnya,
            SUM($komp * total_kubikasi)                          AS n_total,
            SUM(CASE WHEN keterangan LIKE '%".HppVeneerJadiService::TANDA_SEMENTARA."%' THEN 1 ELSE 0 END) AS sementara
        ";
    }

    protected function petaHpp($r): array
    {
        $m3  = (float) $r->m3;
        $per = fn ($n) => $m3 > 0 ? (float) $n / $m3 : 0.0;

        return [
            'bulan'     => $r->bulan,
            'transaksi' => (int) $r->transaksi,
            'm3'        => $m3,
            'kayu'      => $per($r->n_kayu),
            'pekerja'   => $per($r->n_pekerja),
            'mesin'     => $per($r->n_mesin),
            'bahan'     => $per($r->n_bahan),
            'lainnya'   => $per($r->n_lainnya),
            'total'     => $per($r->n_total),
            'sementara' => (int) $r->sementara,
        ];
    }

    /** Angka UTAMA (kartu besar). Kalau bulan belum dipilih → bulan terbaru yang ada datanya. */
    public function getHppRingkasProperty(): array
    {
        $otomatis = ! preg_match('/^\d{4}-\d{2}$/', $this->filterBulan);

        $bulan = $otomatis
            ? $this->queryHppMasuk(false)->reorder()->selectRaw("MAX(DATE_FORMAT(tanggal, '%Y-%m')) AS b")->value('b')
            : $this->filterBulan;

        $jenis = $this->filterJenisKayu !== '' ? (JenisKayu::find($this->filterJenisKayu)?->nama_kayu ?? '-') : 'Semua jenis kayu';
        $ukuran = $this->filterPanjang !== '' && $this->filterLebar !== '' && $this->filterTebal !== ''
            ? (float) $this->filterPanjang.'×'.(float) $this->filterLebar.'×'.(float) $this->filterTebal
            : '';

        $dasar = [
            'bulan'    => $bulan,
            'otomatis' => $otomatis,
            'jenis'    => $jenis,
            'ukuran'   => $ukuran,
            'lengkap'  => $this->filterJenisKayu !== '' && $this->filterPanjang !== '' && $this->filterLebar !== '' && $this->filterTebal !== '',
            'data'     => null,
        ];

        if (! $bulan || ! $dasar['lengkap']) {
            return $dasar;
        }

        $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();

        $row = $this->queryHppMasuk(false)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->reorder()
            ->selectRaw($this->selectHpp())
            ->groupBy('bulan')
            ->first();

        $dasar['data'] = $row ? $this->petaHpp($row) : null;

        return $dasar;
    }

    /** Rekap per UKURAN untuk bulan yang sedang dilihat (saat ukuran belum dipilih lengkap). */
    public function getHppPerUkuranProperty(): array
    {
        $r = $this->hppRingkas;
        if ($r['lengkap'] || ! $r['bulan']) {
            return [];
        }

        $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $r['bulan'].'-01')->startOfMonth();
        $komp = self::KOMP;
        $nama = JenisKayu::pluck('nama_kayu', 'id');

        return $this->queryHppMasuk(false)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->reorder()
            ->selectRaw("
                id_jenis_kayu, panjang, lebar, tebal,
                COUNT(*)            AS transaksi,
                SUM(total_kubikasi) AS m3,
                SUM($komp * total_kubikasi) AS n_total,
                SUM(CASE WHEN keterangan LIKE '%".HppVeneerJadiService::TANDA_SEMENTARA."%' THEN 1 ELSE 0 END) AS sementara
            ")
            ->groupBy('id_jenis_kayu', 'panjang', 'lebar', 'tebal')
            ->orderByDesc('m3')
            ->limit(30)
            ->get()
            ->map(fn ($u) => [
                'jenis_id'  => (string) $u->id_jenis_kayu,
                'jenis'     => $nama[$u->id_jenis_kayu] ?? '-',
                'panjang'   => (string) $u->panjang,
                'lebar'     => (string) $u->lebar,
                'tebal'     => (string) $u->tebal,
                'ukuran'    => (float) $u->panjang.'×'.(float) $u->lebar.'×'.(float) $u->tebal,
                'transaksi' => (int) $u->transaksi,
                'm3'        => (float) $u->m3,
                'total'     => (float) $u->m3 > 0 ? (float) $u->n_total / (float) $u->m3 : 0.0,
                'sementara' => (int) $u->sementara,
            ])
            ->all();
    }

    // ── Asal veneer per baris log (lahan & mesin) ──────────────

    /**
     * Peta id log → ['lahan' => [['kode' => 'A', 'persen' => 62], ...], 'mesin' => [['nama' => 'Dryer', 'persen' => 67], ...]]
     * untuk baris MASUK yang sedang tampil (1 query lahan untuk seluruh halaman).
     */
    public function asalUntukLogs($logs): array
    {
        $masuk = collect($logs->items())->where('tipe_transaksi', 'masuk');

        $idLahan = $masuk
            ->flatMap(fn ($l) => array_keys($l->komposisi['lahan'] ?? []))
            ->filter(fn ($k) => is_numeric($k))
            ->unique()->values();

        $kode = $idLahan->isEmpty() ? collect() : Lahan::whereIn('id', $idLahan)
            ->get(['id', 'kode_lahan', 'nama_lahan'])
            ->mapWithKeys(fn ($l) => [(string) $l->id => $l->kode_lahan ?: $l->nama_lahan]);

        $labelMesin = ['dryer' => 'Dryer', 'kedi' => 'Kedi', 'tidak_diketahui' => '?'];

        $hasil = [];
        foreach ($masuk as $log) {
            $lahan = [];
            foreach ($log->komposisi['lahan'] ?? [] as $id => $porsi) {
                $lahan[] = [
                    'kode'   => is_numeric($id) ? ($kode[(string) $id] ?? "#{$id}") : '?',
                    'persen' => (int) round($porsi * 100),
                ];
            }
            usort($lahan, fn ($a, $b) => $b['persen'] <=> $a['persen']);

            $mesin = [];
            foreach ($log->komposisi['mesin'] ?? [] as $id => $porsi) {
                $mesin[] = ['nama' => $labelMesin[$id] ?? $id, 'persen' => (int) round($porsi * 100)];
            }
            usort($mesin, fn ($a, $b) => $b['persen'] <=> $a['persen']);

            $hasil[$log->id] = ['lahan' => $lahan, 'mesin' => $mesin];
        }

        return $hasil;
    }

    protected function logSearchConfig(): array
    {
        return [
            'text'      => ['keterangan', 'tipe_transaksi'],
            'kw'        => 'kw_grade',
            'relations' => ['jenisKayu.nama_kayu'],
            'size'      => ['panjang', 'lebar', 'tebal'],
            'date'      => 'tanggal',
        ];
    }
}