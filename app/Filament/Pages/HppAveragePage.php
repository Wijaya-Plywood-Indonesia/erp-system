<?php

namespace App\Filament\Pages;

use App\Models\HppAverageSummary;
use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\Lahan;
use App\Services\HppAverageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use Filament\Pages\Page;
use UnitEnum;

class HppAveragePage extends Page
{
    protected string $view = 'filament.pages.hpp-average-page';

    protected static ?string $navigationLabel = 'HPP Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Hpp';
    protected static ?string $title = 'Ringkasan HPP Average';
    protected static ?int $navigationSort = 10;

    // ── State (reactive via Livewire) ──────────────────────────
    public string $activeTab = 'stok';      // 'stok' | 'log'
    public ?int $activeLahanId = null;
    public string $activeJenis = '';
    public string $filterGrade = '';
    public string $filterPanjang = '';
    public string $filterJenis = '';
    public string $logTab = 'semua';     // 'semua' | 'masuk' | 'keluar'
    public string $lahanSearch = '';

    // ── Mount: set lahan default ───────────────────────────────
    public function mount(): void
    {
        $first = HppAverageSummarie::with('lahan')
            ->select('id_lahan')
            ->groupBy('id_lahan')
            ->first();

        $this->activeLahanId = $first?->id_lahan;
    }

    // ── Data untuk view ────────────────────────────────────────

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

    public function getStokSummariesProperty()
    {
        return HppAverageSummarie::with(['lahan', 'jenisKayu'])
            ->where('id_lahan', $this->activeLahanId)
            ->when(
                $this->activeJenis,
                fn($q) =>
                $q->whereHas(
                    'jenisKayu',
                    fn($q2) =>
                    $q2->where('nama', $this->activeJenis)
                )
            )
            ->where('stok_kubikasi', '>', 0)
            ->get();
    }

    public function getActiveLahanProperty()
    {
        return Lahan::find($this->activeLahanId);
    }

    public function getStokPerLahanProperty()
    {
        // Untuk sidebar: total batang & jenis per lahan
        return HppAverageSummarie::with('jenisKayu')
            ->where('stok_kubikasi', '>', 0)
            ->get()
            ->groupBy('id_lahan')
            ->map(fn($rows) => [
                'btg' => $rows->sum('stok_batang'),
                'jenis' => $rows->pluck('jenisKayu.nama_kayu')->unique()->sort()->values(),
                'kom' => $rows->count(),
            ]);
    }

    public function getJenisListProperty()
    {
        // Jenis kayu yang ada di lahan aktif
        return HppAverageSummarie::with('jenisKayu')
            ->where('id_lahan', $this->activeLahanId)
            ->where('stok_kubikasi', '>', 0)
            ->get()
            ->pluck('jenisKayu.nama_kayu')
            ->unique()
            ->sort()
            ->values();
    }

    public function getLogsProperty()
    {
        return HppAverageLog::with(['jenisKayu'])
            ->when($this->filterGrade, fn($q) => $q->where('grade', $this->filterGrade))
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when(
                $this->filterJenis,
                fn($q) =>
                $q->whereHas(
                    'jenisKayu',
                    fn($q2) =>
                    $q2->where('nama', $this->filterJenis)
                )
            )
            ->when(
                $this->logTab !== 'semua',
                fn($q) =>
                $q->where('tipe_transaksi', $this->logTab)
            )
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    public function getLogCountsProperty(): array
    {
        $base = HppAverageLog::query();
        return [
            'semua' => (clone $base)->count(),
            'masuk' => (clone $base)->where('tipe_transaksi', 'masuk')->count(),
            'keluar' => (clone $base)->where('tipe_transaksi', 'keluar')->count(),
        ];
    }

    // ── Actions ────────────────────────────────────────────────

    public function selectLahan(int $lahanId): void
    {
        $this->activeLahanId = $lahanId;
        $this->activeJenis = '';
    }

    public function selectJenis(string $jenis): void
    {
        $this->activeJenis = $this->activeJenis === $jenis ? '' : $jenis;
    }

    public function recalculate(): void
    {
        app(HppAverageService::class)->recalculateAll();

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
