<?php

namespace App\Filament\Pages;

use App\Concerns\FlexibleLogSearch;
use App\Models\DetailHasil;
use App\Models\JenisKayu;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Services\HppDryerService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Livewire\WithPagination;
use UnitEnum;

class HppVeneerKeringPage extends Page
{
    use HasPageShield;
    use FlexibleLogSearch;
    use WithPagination;

    protected string $view = 'filament.pages.hpp-veneer-kering-page';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Log HPP Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log HPP Veneer Kering';
    protected static ?int    $navigationSort = 21;

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
    public string $filterBulan     = '';     // format YYYY-MM, kosong = semua bulan
    public bool   $tampilNilaiStok = false;  // kolom Nilai Stok disembunyikan secara bawaan

    /**
     * Sinkron otomatis: palet dryer yang HPP-nya belum lengkap / m³-nya masih salah
     * (data lama) dihitung ulang memakai rumus terbaru, sehingga tabel selalu sesuai
     * tanpa tombol. Hanya kolom HPP, nilai, dan m³ yang diperbarui — jumlah lembar tidak berubah.
     */
    public function mount(): void
    {
        $this->sinkronHpp();
    }

    protected function sinkronHpp(): void
    {
        try {
            $idDetail = StokVeneerKering::query()
                ->where('jenis_transaksi', 'masuk')
                ->whereNotNull('id_detail_hasil_dryer')
                ->where(function ($q) {
                    $q->where('hpp_veneer_basah_per_m3', '<=', 0)
                        ->orWhere('ongkos_dryer_per_m3', '<=', 0)
                        ->orWhere('hpp_kering_per_m3', '<=', 0)
                        ->orWhereRaw('ABS(m3 - (SELECT u.panjang * u.lebar * u.tebal FROM ukurans u WHERE u.id = stok_veneer_kerings.id_ukuran) * qty / ' . HppDryerService::PEMBAGI_M3 . ') > 0.0005');
                })
                ->limit(2000)
                ->pluck('id_detail_hasil_dryer');

            $idProduksi = DetailHasil::whereIn('id', $idDetail)
                ->pluck('id_produksi_dryer')->filter()->unique()->take(60)->values()->all();

            // Sinkron bertahap satu kali untuk SEMUA palet dryer lama (angka basah lama bisa
            // berasal dari sumber yang salah walau tidak 0). 60 produksi per kunjungan,
            // penanda kemajuan disimpan di cache.
            if (count($idProduksi) < 60) {
                $kunci = 'hpp_kering_sinkron_v4_last_id';
                $lastId = (int) Cache::get($kunci, 0);

                $semua = StokVeneerKering::query()
                    ->join('detail_hasils', 'detail_hasils.id', '=', 'stok_veneer_kerings.id_detail_hasil_dryer')
                    ->where('stok_veneer_kerings.jenis_transaksi', 'masuk')
                    ->where('detail_hasils.id_produksi_dryer', '>', $lastId)
                    ->distinct()
                    ->orderBy('detail_hasils.id_produksi_dryer')
                    ->limit(60 - count($idProduksi))
                    ->pluck('detail_hasils.id_produksi_dryer')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                if ($semua) {
                    Cache::forever($kunci, max($semua));
                    $idProduksi = array_values(array_unique(array_merge($idProduksi, $semua)));
                }
            }

            if ($idProduksi) {
                app(HppDryerService::class)->prosesBanyakProduksi($idProduksi);
            }

            // Sumber masuk lain (pilih veneer, opname, nota, repair, dll) yang komponennya masih kosong
            app(HppDryerService::class)->sinkronSumberLain();
        } catch (\Throwable $e) {
            Log::warning('HppVeneerKeringPage: sinkron HPP gagal — ' . $e->getMessage());

            Notification::make()
                ->title('Sinkron HPP veneer kering gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ── Query dasar (dipakai tabel & ringkasan) ────────────────
    protected function queryLogs(bool $denganBulan = true)
    {
        return StokVeneerKering::query()
            ->when($denganBulan && preg_match('/^\d{4}-\d{2}$/', $this->filterBulan), function ($q) {
                $awal = Carbon::createFromFormat('Y-m-d', $this->filterBulan . '-01')->startOfMonth();
                $q->whereBetween('tanggal_transaksi', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()]);
            })
            ->when($this->filterJenisKayu, fn ($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang, fn ($q) => $q->whereHas('ukuran', fn ($u) => $u->where('panjang', $this->filterPanjang)))
            ->when($this->filterLebar,   fn ($q) => $q->whereHas('ukuran', fn ($u) => $u->where('lebar', $this->filterLebar)))
            ->when($this->filterTebal,   fn ($q) => $q->whereHas('ukuran', fn ($u) => $u->where('tebal', $this->filterTebal)))
            ->when(trim($this->filterKw) !== '', fn ($q) => $this->flexibleSearch($q, $this->filterKw, $this->logSearchConfig()));
    }

    // ── Computed: log transaksi (50 baris per halaman) ─────────
    public function getLogsProperty()
    {
        return $this->queryLogs()
            ->with(['ukuran', 'jenisKayu'])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->paginate(50);
    }

    // ── Computed: ringkasan SELURUH hasil filter (dihitung di database) ──
    public function getRingkasanProperty(): array
    {
        $agg = $this->queryLogs()
            ->reorder()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'masuk'  THEN qty             ELSE 0 END), 0) AS masuk_lbr,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'keluar' THEN qty             ELSE 0 END), 0) AS keluar_lbr,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'masuk'  THEN m3              ELSE 0 END), 0) AS masuk_m3,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'keluar' THEN m3              ELSE 0 END), 0) AS keluar_m3,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'masuk'  THEN nilai_transaksi ELSE 0 END), 0) AS masuk_nilai,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'keluar' THEN nilai_transaksi ELSE 0 END), 0) AS keluar_nilai
            ")
            ->first();

        $last = $this->queryLogs()->orderByDesc('tanggal_transaksi')->orderByDesc('id')->first();

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
        return StokVeneerKering::query()
            ->selectRaw("DATE_FORMAT(tanggal_transaksi, '%Y-%m') AS bln")
            ->distinct()
            ->orderByDesc('bln')
            ->pluck('bln');
    }

    // ── Computed: ukuran unik untuk filter ────────────────────
    public function getUkuranListProperty()
    {
        $ukuranIds = StokVeneerKering::select('id_ukuran')->distinct()->pluck('id_ukuran');

        return Ukuran::whereIn('id', $ukuranIds)
            ->select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
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

    // =========================================================================
    // KOMPONEN HPP KERING = BASAH + PEGAWAI + MESIN  (per m³)
    // =========================================================================

    /**
     * Rincian HPP per m³ (basah, pegawai, mesin) untuk baris MASUK dari SEMUA sumber:
     * Press Dryer, pilih veneer, opname, nota/mutasi, repair/serah terima, dll.
     * Perhitungannya ada di HppDryerService::komponenBaris().
     */
    public function komponenBaris(iterable $rows): array
    {
        return app(HppDryerService::class)->komponenBaris($rows);
    }

    /** Baris masuk (semua sumber) yang sudah punya HPP kering — dasar semua hitungan HPP bulanan. */
    protected function queryHppMasuk(bool $denganBulan)
    {
        return $this->queryLogs($denganBulan)
            ->where('jenis_transaksi', 'masuk')
            ->where('hpp_kering_per_m3', '>', 0);
    }

    /** Baris palet masuk dryer berHPP pada satu bulan (sesuai filter lain) + rincian komponennya. */
    protected function barisHppBulan(string $bulan): Collection
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();

        $rows = $this->queryHppMasuk(false)
            ->whereBetween('tanggal_transaksi', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->with(['ukuran:id,panjang,lebar,tebal'])
            ->reorder()
            ->get(['id', 'id_ukuran', 'id_jenis_kayu', 'kw', 'tanggal_transaksi', 'keterangan', 'id_veneer_mutasi',
                'id_detail_hasil_dryer', 'jenis_transaksi', 'm3',
                'hpp_veneer_basah_per_m3', 'ongkos_dryer_per_m3', 'hpp_kering_per_m3']);

        $komp = $this->komponenBaris($rows);

        return $rows->map(function ($r) use ($komp) {
            $r->komp = $komp[$r->id] ?? null;

            return $r;
        })->filter(fn ($r) => $r->komp !== null)->values();
    }

    /** Hitung rata-rata tertimbang m³ dari sekumpulan baris (hasil barisHppBulan). */
    protected function petaHpp(Collection $rows, ?string $bulan = null): array
    {
        $m3 = (float) $rows->sum(fn ($r) => (float) $r->m3);
        $per = fn (string $k) => $m3 > 0
            ? (float) $rows->sum(fn ($r) => $r->komp[$k] * (float) $r->m3) / $m3
            : 0.0;

        return [
            'bulan'     => $bulan,
            'palet'     => $rows->count(),
            'm3'        => $m3,
            'basah'     => $per('basah'),
            'pegawai'   => $per('pegawai'),
            'mesin'     => $per('mesin'),
            'total'     => $per('total'),
            'sementara' => $rows->filter(fn ($r) => ! $r->komp['final'])->count(),
            // palet yang ongkos pegawai atau mesinnya masih 0 (belum diinput) → angkanya belum lengkap
            'kurang'    => $rows->filter(fn ($r) => $r->komp['pegawai'] <= 0 || $r->komp['mesin'] <= 0)->count(),
        ];
    }

    /**
     * Angka UTAMA (kartu besar): HPP kering rata-rata untuk SEMUA filter yang aktif,
     * termasuk bulan. Kalau bulan belum dipilih → bulan terbaru yang ada datanya.
     */
    public function getHppRingkasProperty(): array
    {
        $otomatis = ! preg_match('/^\d{4}-\d{2}$/', $this->filterBulan);

        $bulan = $otomatis
            ? $this->queryHppMasuk(false)->reorder()->selectRaw("MAX(DATE_FORMAT(tanggal_transaksi, '%Y-%m')) AS b")->value('b')
            : $this->filterBulan;

        $jenis = $this->filterJenisKayu !== '' ? (JenisKayu::find($this->filterJenisKayu)?->nama_kayu ?? '-') : 'Semua jenis kayu';
        $ukuran = $this->filterPanjang !== '' && $this->filterLebar !== '' && $this->filterTebal !== ''
            ? (float) $this->filterPanjang . '×' . (float) $this->filterLebar . '×' . (float) $this->filterTebal
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

        $rows = $this->barisHppBulan($bulan);

        $dasar['data'] = $rows->isNotEmpty() ? $this->petaHpp($rows, $bulan) : null;

        return $dasar;
    }

    /**
     * Rekap per UKURAN untuk bulan yang sedang dilihat (dipakai saat ukuran belum dipilih lengkap).
     * Satu baris = satu jenis kayu + ukuran: HPP kering rata-rata (tertimbang m³) dari palet masuk bulan itu.
     */
    public function getHppPerUkuranProperty(): array
    {
        $r = $this->hppRingkas;
        if ($r['lengkap'] || ! $r['bulan']) {
            return [];
        }

        $nama = JenisKayu::pluck('nama_kayu', 'id');

        return $this->barisHppBulan($r['bulan'])
            ->filter(fn ($row) => $row->ukuran)
            ->groupBy(fn ($row) => $row->id_jenis_kayu . '|' . (float) $row->ukuran->panjang . '|' . (float) $row->ukuran->lebar . '|' . (float) $row->ukuran->tebal)
            ->map(function (Collection $g) use ($nama) {
                $first = $g->first();
                $p = $this->petaHpp($g);

                return [
                    'jenis_id'  => (string) $first->id_jenis_kayu,
                    'jenis'     => $nama[$first->id_jenis_kayu] ?? '-',
                    'panjang'   => (string) $first->ukuran->panjang,
                    'lebar'     => (string) $first->ukuran->lebar,
                    'tebal'     => (string) $first->ukuran->tebal,
                    'ukuran'    => (float) $first->ukuran->panjang . '×' . (float) $first->ukuran->lebar . '×' . (float) $first->ukuran->tebal,
                    'palet'     => $p['palet'],
                    'm3'        => $p['m3'],
                    'total'     => $p['total'],
                    'sementara' => $p['sementara'],
                    'kurang'    => $p['kurang'],
                ];
            })
            ->sortByDesc('m3')
            ->take(30)
            ->values()
            ->all();
    }

    protected function logSearchConfig(): array
    {
        return [
            'text'          => ['keterangan', 'jenis_transaksi'],
            'kw'            => 'kw',
            'relations'     => ['jenisKayu.nama_kayu'],
            'size'          => ['panjang', 'lebar', 'tebal'],
            'size_relation' => 'ukuran',
            'date'          => 'tanggal_transaksi',
        ];
    }
}
