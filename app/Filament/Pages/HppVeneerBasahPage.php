<?php

namespace App\Filament\Pages;

use App\Concerns\FlexibleLogSearch;
use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahLog;
use App\Models\JenisKayu;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Livewire\WithPagination;
use UnitEnum;
use Filament\Support\Enums\Width;

class HppVeneerBasahPage extends Page
{
    use HasPageShield;
    use FlexibleLogSearch;
    use WithPagination;
    protected string $view = 'filament.pages.hpp-veneer-basah-page';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Log HPP Veneer Basah';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log HPP Veneer Basah';
    protected static ?int    $navigationSort = 11;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang   = '';
    public string $filterLebar     = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';
    public string $filterBulan     = '';   // format YYYY-MM, kosong = semua bulan
    public bool   $tampilNilaiStok = false;  // kolom Nilai Stok disembunyikan secara bawaan

    // ── Query dasar (dipakai tabel & ringkasan) ────────────────
    protected function queryLogs(bool $denganBulan = true)
    {
        return HppVeneerBasahLog::query()
            ->when($denganBulan && preg_match('/^\d{4}-\d{2}$/', $this->filterBulan), function ($q) {
                $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $this->filterBulan.'-01')->startOfMonth();
                $q->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()]);
            })
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang,   fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterLebar,     fn($q) => $q->where('lebar',   $this->filterLebar))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',   $this->filterTebal))
            ->when(trim($this->filterKw) !== '', fn($q) => $this->flexibleSearch($q, $this->filterKw, $this->logSearchConfig()));
    }

    // ── Computed: log transaksi (50 baris per halaman) ─────────
    public function getLogsProperty()
    {
        return $this->queryLogs()
            ->with('jenisKayu')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(50);
    }

    // ── Computed: ringkasan SELURUH hasil filter (dihitung di DB) ──
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
    public function updatedFilterPanjang(): void   { $this->resetPage(); }
    public function updatedFilterLebar(): void     { $this->resetPage(); }
    public function updatedFilterTebal(): void     { $this->resetPage(); }
    public function updatedFilterKw(): void        { $this->resetPage(); }
    public function updatedFilterBulan(): void     { $this->resetPage(); }

    // ── Computed: daftar bulan yang punya log (untuk filter) ───
    public function getBulanListProperty()
    {
        return HppVeneerBasahLog::query()
            ->selectRaw("DATE_FORMAT(tanggal, '%Y-%m') AS bln")
            ->distinct()
            ->orderByDesc('bln')
            ->pluck('bln');
    }

    /**
     * Rekap per UKURAN untuk bulan yang sedang dilihat (dipakai saat ukuran belum dipilih lengkap).
     * Satu baris = satu jenis kayu + ukuran: HPP rata-rata (tertimbang m³) dari palet masuk bulan itu.
     */
    public function getHppPerUkuranProperty(): array
    {
        $r = $this->hppRingkas;
        if ($r['lengkap'] || ! $r['bulan']) {
            return [];
        }

        $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $r['bulan'].'-01')->startOfMonth();
        $komp = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0))';
        $nama = JenisKayu::pluck('nama_kayu', 'id');

        return $this->queryHppMasuk(false)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->reorder()
            ->selectRaw("
                id_jenis_kayu, panjang, lebar, tebal,
                COUNT(*)            AS palet,
                SUM(total_kubikasi) AS m3,
                SUM($komp * total_kubikasi) AS n_total,
                SUM(CASE WHEN keterangan LIKE '%[HPP SEMENTARA]%' THEN 1 ELSE 0 END) AS sementara
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
                'palet'     => (int) $u->palet,
                'm3'        => (float) $u->m3,
                'total'     => (float) $u->m3 > 0 ? (float) $u->n_total / (float) $u->m3 : 0.0,
                'sementara' => (int) $u->sementara,
            ])
            ->all();
    }

    /** Kosongkan semua filter sekaligus. */
    public function resetFilter(): void
    {
        $this->filterJenisKayu = '';
        $this->filterPanjang   = '';
        $this->filterLebar     = '';
        $this->filterTebal     = '';
        $this->filterKw        = '';
        $this->filterBulan     = '';
        $this->resetPage();
    }

    /** Klik baris rekap → pilih jenis kayu + ukuran tersebut di filter. */
    public function pilihUkuran(string $jenis, string $panjang, string $lebar, string $tebal): void
    {
        $this->filterJenisKayu = $jenis;
        $this->filterPanjang   = $panjang;
        $this->filterLebar     = $lebar;
        $this->filterTebal     = $tebal;
        $this->resetPage();
    }

    /**
     * Angka UTAMA (kartu besar): HPP rata-rata untuk SEMUA filter yang aktif,
     * termasuk bulan. Kalau bulan belum dipilih → bulan terbaru yang ada datanya.
     */
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

    /** Log MASUK palet yang sudah punya komponen HPP (dasar semua hitungan HPP bulanan). */
    protected function queryHppMasuk(bool $denganBulan)
    {
        $komp = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0))';

        return $this->queryLogs($denganBulan)
            ->where('tipe_transaksi', 'masuk')
            ->where('referensi_type', DetailHasilPaletRotary::class)
            ->whereRaw("$komp > 0");
    }

    protected function selectHpp(): string
    {
        $komp = '(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0))';

        return "
            DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
            COUNT(*)                                             AS palet,
            SUM(total_kubikasi)                                  AS m3,
            SUM(COALESCE(hpp_kayu,0)           * total_kubikasi) AS n_kayu,
            SUM(COALESCE(hpp_pekerja,0)        * total_kubikasi) AS n_pekerja,
            SUM(COALESCE(hpp_mesin,0)          * total_kubikasi) AS n_mesin,
            SUM(COALESCE(hpp_bahan_penolong,0) * total_kubikasi) AS n_bahan,
            SUM($komp * total_kubikasi)                          AS n_total,
            SUM(CASE WHEN keterangan LIKE '%[HPP SEMENTARA]%' THEN 1 ELSE 0 END) AS sementara
        ";
    }

    protected function petaHpp($r): array
    {
        $m3 = (float) $r->m3;
        $per = fn ($n) => $m3 > 0 ? (float) $n / $m3 : 0.0;

        return [
            'bulan'     => $r->bulan,
            'palet'     => (int) $r->palet,
            'm3'        => $m3,
            'kayu'      => $per($r->n_kayu),
            'pekerja'   => $per($r->n_pekerja),
            'mesin'     => $per($r->n_mesin),
            'bahan'     => $per($r->n_bahan),
            'total'     => $per($r->n_total),
            'sementara' => (int) $r->sementara,
        ];
    }

    /** Peta id palet → KODE lahan (A, B, KA, ...), untuk baris log yang sedang tampil (1 query). */
    public function lahanUntukLogs($logs): array
    {
        $ids = collect($logs->items())
            ->where('referensi_type', DetailHasilPaletRotary::class)
            ->pluck('referensi_id')->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DetailHasilPaletRotary::with('penggunaanLahan.lahan:id,nama_lahan,kode_lahan')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->penggunaanLahan?->lahan?->kode_lahan ?? $p->penggunaanLahan?->lahan?->nama_lahan])
            ->filter()
            ->all();
    }

    // ── Computed: ukuran unik untuk filter ────────────────────
    public function getUkuranListProperty()
    {
        return HppVeneerBasahLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }

    protected function logSearchConfig(): array
{
    return [
        'text'      => ['keterangan', 'tipe_transaksi'],
        'kw'        => 'kw',            // di HppVeneerBasahPage ganti jadi 'kw'
        'relations' => ['jenisKayu.nama_kayu'],
        'size'      => ['panjang', 'lebar', 'tebal'],
        'date'      => 'tanggal',
    ];
}
}