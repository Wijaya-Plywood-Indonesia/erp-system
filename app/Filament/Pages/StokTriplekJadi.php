<?php

namespace App\Filament\Pages;

use App\Models\StokTriplekJadi as StokTriplekJadiModel;
use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\WipSandingReset;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokTriplekJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-triplek-jadi';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Stok Triplek Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Triplek Jadi';
    protected static ?int    $navigationSort = 8;

    // Tujuan keluar yang dihitung sebagai WIP sanding — harus cocok dengan
    // nilai yang disimpan di TriplekJadiMutasiKeluar.tujuan saat keluar ke sanding.
    private const TUJUAN_SANDING = 'Produksi Sanding';

    // State untuk filtering di UI Blade
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    public bool $showKubikasi   = false;
    public bool $showHppAverage = false;
    public bool $showNilaiStok  = false;

    public function getSummariesProperty()
    {
        return StokTriplekJadiModel::with(['jenisKayu', 'lastLog'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw_grade', $this->filterKw))
            ->where('stok_lembar', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    public function getKwListProperty()
    {
        return StokTriplekJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('kw_grade');
    }

    public function getTebalListProperty()
    {
        return StokTriplekJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return (float) StokTriplekJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('nilai_stok');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) StokTriplekJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_lembar');
    }

    // ─── WIP SANDING PER SPESIFIKASI ─────────────────────────────────────────

    /**
     * Kunci unik satu spesifikasi barang. Karena Anda konfirmasi spesifikasi
     * barang yang disanding TIDAK berubah (5mm keluar, 5mm kembali), kunci ini
     * cocok antara sisi keluar dan sisi kembali tanpa ambiguitas.
     */
    public static function specKey($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade): string
    {
        return implode('|', [
            (int) $idJenisKayu,
            (float) $panjang,
            (float) $lebar,
            (float) $tebal,
            (string) $kwGrade,
        ]);
    }

    /**
     * Map WIP sanding per spesifikasi: [specKey => jumlah_lembar_wip].
     *
     * WIP = Σ(keluar ke sanding) − Σ(kembali dari sanding), dikelompokkan per
     * spesifikasi, DIKURANGI baseline yang sudah "ditutup buku" via tombol reset.
     *
     * Kenapa pakai baseline, bukan sekadar mengecualikan mutasi:
     * karena hasil sanding yang KEMBALI tidak tertaut ke mutasi keluar (barang
     * tercampur di sanding — many-to-many). Kalau mutasi keluar dikecualikan tapi
     * "kembali" tetap dihitung penuh, WIP bisa jadi minus. Baseline mencatat
     * "keluar" dan "kembali" pada saat reset, lalu WIP hanya menghitung selisih
     * SETELAH baseline. Ini tahan-bocor dan tidak pernah minus.
     *
     * Baseline disimpan di tabel wip_sanding_resets (spesifikasi + kumulatif saat reset).
     *
     * Dihitung sekali lalu di-cache untuk seluruh request.
     */
    protected ?array $wipCache = null;

    public function getWipMapProperty(): array
    {
        if ($this->wipCache !== null) {
            return $this->wipCache;
        }

        $keluar = [];  // total keluar ke sanding, per spec (sepanjang waktu)
        $masuk  = [];  // total kembali dari sanding, per spec (sepanjang waktu)

        // ── Sisi KELUAR: log 'keluar', referensi mutasi bertujuan sanding ──
        $idMutasiSanding = TriplekJadiMutasiKeluar::where('tujuan', self::TUJUAN_SANDING)
            ->pluck('id');

        if ($idMutasiSanding->isNotEmpty()) {
            HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['keluar'])
                ->where('referensi_type', TriplekJadiMutasiKeluar::class)
                ->whereIn('referensi_id', $idMutasiSanding)
                ->get(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'total_lembar'])
                ->each(function ($log) use (&$keluar) {
                    $key = self::specKey($log->id_jenis_kayu, $log->panjang, $log->lebar, $log->tebal, $log->kw_grade);
                    $keluar[$key] = ($keluar[$key] ?? 0) + (float) $log->total_lembar;
                });
        }

        // ── Sisi KEMBALI: log 'masuk', referensi serah terima dari sanding ──
        $idSerahDariSanding = SerahTerimaTriplekJadi::whereNotNull('id_hasil_sanding')
            ->pluck('id');

        if ($idSerahDariSanding->isNotEmpty()) {
            HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['masuk'])
                ->where('referensi_type', SerahTerimaTriplekJadi::class)
                ->whereIn('referensi_id', $idSerahDariSanding)
                ->get(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'total_lembar'])
                ->each(function ($log) use (&$masuk) {
                    $key = self::specKey($log->id_jenis_kayu, $log->panjang, $log->lebar, $log->tebal, $log->kw_grade);
                    $masuk[$key] = ($masuk[$key] ?? 0) + (float) $log->total_lembar;
                });
        }

        // ── Baseline reset: kumulatif keluar/masuk yang sudah "ditutup buku" ──
        $baselineKeluar = [];
        $baselineMasuk  = [];
        WipSandingReset::all(['spec_key', 'keluar_kumulatif', 'masuk_kumulatif'])
            ->each(function ($r) use (&$baselineKeluar, &$baselineMasuk) {
                // Kalau satu spec direset berkali-kali, ambil baseline TERBESAR
                // (reset paling akhir menutup paling banyak).
                $baselineKeluar[$r->spec_key] = max($baselineKeluar[$r->spec_key] ?? 0, (float) $r->keluar_kumulatif);
                $baselineMasuk[$r->spec_key]  = max($baselineMasuk[$r->spec_key] ?? 0, (float) $r->masuk_kumulatif);
            });

        // ── WIP = (keluar − baselineKeluar) − (masuk − baselineMasuk) ──
        $map = [];
        foreach ($keluar as $key => $totalKeluar) {
            $netKeluar = $totalKeluar - ($baselineKeluar[$key] ?? 0);
            $netMasuk  = ($masuk[$key] ?? 0) - ($baselineMasuk[$key] ?? 0);
            $wip = $netKeluar - $netMasuk;

            if ($wip > 0) {
                $map[$key] = $wip;
            }
        }

        return $this->wipCache = $map;
    }

    /**
     * Data mentah keluar & masuk kumulatif untuk satu spesifikasi — dipakai
     * saat menekan tombol reset, untuk mencatat baseline.
     */
    protected function kumulatifSpec(string $specKey): array
    {
        $keluar = 0.0;
        $masuk  = 0.0;

        $idMutasiSanding = TriplekJadiMutasiKeluar::where('tujuan', self::TUJUAN_SANDING)->pluck('id');
        if ($idMutasiSanding->isNotEmpty()) {
            HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['keluar'])
                ->where('referensi_type', TriplekJadiMutasiKeluar::class)
                ->whereIn('referensi_id', $idMutasiSanding)
                ->get(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'total_lembar'])
                ->each(function ($log) use (&$keluar, $specKey) {
                    if (self::specKey($log->id_jenis_kayu, $log->panjang, $log->lebar, $log->tebal, $log->kw_grade) === $specKey) {
                        $keluar += (float) $log->total_lembar;
                    }
                });
        }

        $idSerah = SerahTerimaTriplekJadi::whereNotNull('id_hasil_sanding')->pluck('id');
        if ($idSerah->isNotEmpty()) {
            HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['masuk'])
                ->where('referensi_type', SerahTerimaTriplekJadi::class)
                ->whereIn('referensi_id', $idSerah)
                ->get(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'total_lembar'])
                ->each(function ($log) use (&$masuk, $specKey) {
                    if (self::specKey($log->id_jenis_kayu, $log->panjang, $log->lebar, $log->tebal, $log->kw_grade) === $specKey) {
                        $masuk += (float) $log->total_lembar;
                    }
                });
        }

        return ['keluar' => $keluar, 'masuk' => $masuk];
    }

    /**
     * Tombol "Selesaikan WIP" untuk satu spesifikasi. Menutup buku: mencatat
     * kumulatif keluar/masuk saat ini sebagai baseline, sehingga WIP spec ini
     * jadi 0. Sisa yang belum kembali dianggap susut tercatat.
     */
    public function selesaikanWip($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade): void
    {
        $specKey = self::specKey($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);
        $kum = $this->kumulatifSpec($specKey);

        WipSandingReset::create([
            'spec_key'         => $specKey,
            'id_jenis_kayu'    => $idJenisKayu,
            'panjang'          => $panjang,
            'lebar'            => $lebar,
            'tebal'            => $tebal,
            'kw_grade'         => $kwGrade,
            'keluar_kumulatif' => $kum['keluar'],
            'masuk_kumulatif'  => $kum['masuk'],
            'direset_oleh'     => auth()->id(),
        ]);

        $this->wipCache = null; // paksa hitung ulang

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('WIP diselesaikan')
            ->body('Sisa WIP untuk spesifikasi ini ditandai selesai (susut tercatat).')
            ->send();
    }

    /**
     * WIP untuk satu baris stok (dipanggil dari Blade per baris).
     */
    public function wipUntuk($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade): float
    {
        $key = self::specKey($idJenisKayu, $panjang, $lebar, $tebal, $kwGrade);

        return (float) ($this->wipMap[$key] ?? 0);
    }
}