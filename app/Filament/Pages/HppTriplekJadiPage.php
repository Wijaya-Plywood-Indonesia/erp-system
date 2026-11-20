<?php

namespace App\Filament\Pages;

use App\Data\HppTriplekJadiLogFilters;
use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use App\Services\HppTriplekJadiLogQuery;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use UnitEnum;

class HppTriplekJadiPage extends Page
{
    use HasPageShield;
    use WithPagination;

    protected const FILTER_CACHE_TTL_MINUTES = 10;

    protected string $view = 'filament.pages.hpp-triplek-jadi-page';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Log HPP Triplek Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title = 'Log HPP Triplek Jadi';
    protected static ?int $navigationSort = 22;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang = '';
    public string $filterTebal = '';
    public string $filterKw = '';
    public string $filterTanggalMulai = '';
    public string $filterTanggalSelesai = '';

    public bool $summaryLoaded = false;

    public function loadSummary(): void
    {
        $this->summaryLoaded = true;
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->resetPage();
            $this->deferSummary();
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'filterJenisKayu',
            'filterPanjang',
            'filterTebal',
            'filterKw',
            'filterTanggalMulai',
            'filterTanggalSelesai',
        ]);
        $this->resetPage();
        $this->deferSummary();
    }

    /** Kosongkan footer lalu minta browser memuatnya di request berikutnya. */
    private function deferSummary(): void
    {
        $this->summaryLoaded = false;
        $this->js('$wire.loadSummary()');
    }

    protected function currentFilters(): HppTriplekJadiLogFilters
    {
        return new HppTriplekJadiLogFilters(
            jenisKayu: $this->filterJenisKayu,
            panjang: $this->filterPanjang,
            tebal: $this->filterTebal,
            kw: $this->filterKw,
            tanggalMulai: $this->filterTanggalMulai,
            tanggalSelesai: $this->filterTanggalSelesai,
        );
    }

    #[Computed]
    public function logs(): Paginator
    {
        return app(HppTriplekJadiLogQuery::class)->paginate($this->currentFilters());
    }

    #[Computed]
    public function summary(): ?array
    {
        return $this->summaryLoaded
            ? app(HppTriplekJadiLogQuery::class)->summary($this->currentFilters())
            : null;
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->currentFilters()->hasActive();
    }

    // ── Opsi filter (di-cache) ─────────────────────────────────
    #[Computed]
    public function jenisKayuOptions(): array
    {
        return Cache::remember(
            'hpp_triplek_jadi_log.filter.jenis_kayu',
            now()->addMinutes(self::FILTER_CACHE_TTL_MINUTES),
            fn() => JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')->all()
        );
    }

    #[Computed]
    public function lengthOptions(): array
    {
        return Cache::remember(
            'hpp_triplek_jadi_log.filter.panjang',
            now()->addMinutes(self::FILTER_CACHE_TTL_MINUTES),
            fn() => HppTriplekJadiLog::query()->distinct()->orderBy('panjang')->pluck('panjang')->all()
        );
    }

    #[Computed]
    public function thicknessOptions(): array
    {
        return Cache::remember(
            'hpp_triplek_jadi_log.filter.tebal',
            now()->addMinutes(self::FILTER_CACHE_TTL_MINUTES),
            fn() => HppTriplekJadiLog::query()->distinct()->orderBy('tebal')->pluck('tebal')->all()
        );
    }
}
