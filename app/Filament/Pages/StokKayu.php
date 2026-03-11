<?php

namespace App\Filament\Pages;

use App\Models\HppAverageSummarie;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Services\HppAverageService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use UnitEnum;

use Filament\Pages\Page;

class StokKayu extends Page
{
    protected string $view = 'filament.pages.stok-kayu';

    protected static ?string $navigationLabel = 'Stok Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Stok Kayu';
    protected static ?string $title          = 'Stok Kayu';
    protected static ?int    $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public ?int   $activeLahanId = null;   // null = semua lahan (global)
    public string $filterPanjang = '';
    public string $filterGrade   = '';
    public string $filterJenis   = '';
    public string $lahanSearch   = '';

    // ── Mount: default = global (null) ─────────────────────────
    public function mount(): void
    {
        $this->activeLahanId = null;
    }

    // ── Computed: semua lahan ──────────────────────────────────
    public function getLahansProperty()
    {
        return Lahan::query()
            ->when(
                $this->lahanSearch,
                fn($q) =>
                $q->where('nama_lahan', 'like', "%{$this->lahanSearch}%")
                    ->orWhere('kode_lahan', 'like', "%{$this->lahanSearch}%")
            )
            ->get();
    }

    // ── Computed: lahan aktif (null = global) ──────────────────
    public function getActiveLahanProperty()
    {
        return $this->activeLahanId ? Lahan::find($this->activeLahanId) : null;
    }

    // ── Computed: ringkasan stok per lahan (untuk sidebar) ─────
    public function getStokPerLahanProperty()
    {
        return HppAverageSummarie::with('jenisKayu')
            ->where('stok_batang', '>', 0)
            ->get()
            ->groupBy('id_lahan')
            ->map(fn($rows) => [
                'btg'   => $rows->sum('stok_batang'),
                'jenis' => $rows->pluck('jenisKayu.nama_kayu')->filter()->unique()->sort()->values(),
            ]);
    }

    // ── Computed: baris stok (filter aware) ───────────────────
    public function getSummariesProperty()
    {
        return HppAverageSummarie::with(['lahan', 'jenisKayu'])
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterGrade,   fn($q) => $q->where('grade',   $this->filterGrade))
            ->when(
                $this->filterJenis,
                fn($q) =>
                $q->whereHas(
                    'jenisKayu',
                    fn($q2) =>
                    $q2->where('nama_kayu', $this->filterJenis)
                )
            )
            ->where('stok_batang', '>', 0)
            ->get();
    }

    // ── Computed: daftar panjang unik (untuk filter chip) ──────
    public function getPanjangListProperty()
    {
        return HppAverageSummarie::query()
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->where('stok_batang', '>', 0)
            ->distinct()
            ->orderBy('panjang')
            ->pluck('panjang');
    }

    // ── Computed: daftar jenis kayu unik (untuk filter chip) ───
    public function getJenisListProperty()
    {
        return HppAverageSummarie::with('jenisKayu')
            ->when($this->activeLahanId, fn($q) => $q->where('id_lahan', $this->activeLahanId))
            ->where('stok_batang', '>', 0)
            ->get()
            ->pluck('jenisKayu.nama_kayu')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    // ── Computed: summaries digroup per panjang ────────────────
    public function getGroupedSummariesProperty()
    {
        return $this->summaries
            ->groupBy('panjang')
            ->sortKeys();
    }

    // ── Computed: lahan yang memiliki stok per kombinasi ───────
    // Dipakai di card "Ada di: HA OA" saat mode global
    public function getLahanPerKombinasiProperty()
    {
        if ($this->activeLahanId) {
            return collect(); // tidak perlu saat per-lahan
        }

        return HppAverageSummarie::with('lahan')
            ->where('stok_batang', '>', 0)
            ->get()
            ->groupBy(fn($r) => $r->id_jenis_kayu . '_' . $r->grade . '_' . $r->panjang)
            ->map(
                fn($rows) =>
                $rows->pluck('lahan.kode_lahan')->filter()->unique()->sort()->values()
            );
    }

    // ── Actions ────────────────────────────────────────────────
    public function selectLahan(?int $lahanId): void
    {
        $this->activeLahanId = $lahanId;
        // reset filter saat ganti lahan
        $this->filterPanjang = '';
        $this->filterGrade   = '';
        $this->filterJenis   = '';
    }

    public function recalculate(): void
    {
        $service = app(HppAverageService::class);

        if (\App\Models\HppAverageLog::count() === 0) {
            $service->seedFromNotaKayu();
        } else {
            $service->recalculateAll();
        }

        Notification::make()
            ->title('HPP Average berhasil dihitung ulang')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Hitung Ulang HPP')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hitung Ulang HPP Average?')
                ->modalDescription('Seluruh snapshot akan dihitung ulang dari awal. Pastikan semua nota kayu sudah benar.')
                ->action(fn() => $this->recalculate()),
        ];
    }
}
