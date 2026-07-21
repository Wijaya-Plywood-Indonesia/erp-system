<?php

namespace App\Filament\Pages;

use App\Models\StokTriplekJadi as StokTriplekJadiModel;
use App\Models\JenisKayu;
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
}
