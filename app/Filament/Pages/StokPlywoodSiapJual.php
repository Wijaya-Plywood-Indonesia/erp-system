<?php

namespace App\Filament\Pages;

use App\Models\StokPlywoodSiapJual as StokPlywoodSiapJualModel;
use App\Models\JenisKayu;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokPlywoodSiapJual extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-plywood-siap-jual';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Stok Plywood Siap Jual';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Plywood Siap Jual';
    protected static ?int    $navigationSort = 10;

    // State untuk filtering di UI Blade
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    public bool $showKubikasi = false;

    public function getSummariesProperty()
    {
        return StokPlywoodSiapJualModel::with(['jenisKayu', 'lastLog'])
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
        return StokPlywoodSiapJualModel::where('stok_lembar', '>', 0)->distinct()->pluck('kw_grade');
    }

    public function getTebalListProperty()
    {
        return StokPlywoodSiapJualModel::where('stok_lembar', '>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) StokPlywoodSiapJualModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_lembar');
    }

    public function getTotalKubikasiProperty(): float
    {
        return (float) StokPlywoodSiapJualModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_kubikasi');
    }
}
