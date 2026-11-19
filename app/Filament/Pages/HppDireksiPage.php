<?php

namespace App\Filament\Pages;

use App\Services\HppGlobalService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class HppDireksiPage extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.hpp-direksi-page';

    protected static ?string $navigationLabel = 'HPP Direksi';
    protected static string|UnitEnum|null $navigationGroup = 'HPP';
    protected static ?string $title = 'HPP Direksi';
    protected static ?int $navigationSort = 1;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /** Bulan yang dilihat (format YYYY-MM). */
    public string $bulan = '';

    public function mount(): void
    {
        $this->bulan = $this->bulanList[0] ?? '';
    }

    /** Daftar bulan yang punya data, terbaru di atas. */
    public function getBulanListProperty(): array
    {
        return app(HppGlobalService::class)->bulanTersedia();
    }

    /** Seluruh tahap & kartunya untuk bulan yang dipilih. */
    public function getTahapProperty(): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            return [];
        }

        return app(HppGlobalService::class)->semuaTahap($this->bulan);
    }
}