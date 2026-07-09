<?php

namespace App\Filament\Pages;

use App\Models\StokPlatformJadi as StokPlatformJadiModel;
use App\Models\JenisBarang;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokPlatformJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-platform-jadi';

    protected static ?string $navigationLabel = 'Stok Platform Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Platform Jadi';
    protected static ?int    $navigationSort = 19;

    // State untuk filtering di UI Blade
    public string $filterJenisBarang = '';
    public string $filterTebal       = '';
    public string $filterKw          = '';

    public bool $showKubikasi   = false;
public bool $showHppAverage = false;
public bool $showNilaiStok  = false;

    public function getSummariesProperty()
    {
        return StokPlatformJadiModel::with(['jenisBarang', 'lastLog'])
            ->when($this->filterJenisBarang, fn($q) => $q->where('id_jenis_barang', $this->filterJenisBarang))
            ->when($this->filterTebal,       fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,          fn($q) => $q->where('kw_grade', $this->filterKw))
            ->where('stok_lembar', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    public function getKwListProperty()
    {
        return StokPlatformJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('kw_grade');
    }

    public function getTebalListProperty()
    {
        return StokPlatformJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return (float) StokPlatformJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisBarang, fn($q) => $q->where('id_jenis_barang', $this->filterJenisBarang))
            ->sum('nilai_stok');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) StokPlatformJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisBarang, fn($q) => $q->where('id_jenis_barang', $this->filterJenisBarang))
            ->sum('stok_lembar');
    }
}