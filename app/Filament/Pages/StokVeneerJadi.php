<?php

namespace App\Filament\Pages;

use App\Models\StokVeneerJadi as StokVeneerJadiModel;
use App\Models\JenisKayu;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokVeneerJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-veneer-jadi';

    protected static ?string $navigationLabel = 'Stok Veneer Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Jadi';
    protected static ?int    $navigationSort = 4;

    // State untuk filtering di UI Blade
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';
    public string $filterCoreType  = '';

    public bool $showKubikasi   = false;
    public bool $showHppAverage = false;
    public bool $showNilaiStok  = false;

    public function getSummariesProperty()
    {
        return StokVeneerJadiModel::with(['jenisKayu', 'lastLog'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw_grade', $this->filterKw))
            ->when(
                $this->filterCoreType === 'long',
                fn($q) =>
                $q->where('panjang', 244)->where('lebar', 122)->where('tebal', '>', 1)
            )
            ->when(
                $this->filterCoreType === 'short',
                fn($q) =>
                $q->where('panjang', 122)->where('lebar', 244)->where('tebal', '>', 1)
            )
            ->where('stok_lembar', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    public function getKwListProperty()
    {
        return StokVeneerJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('kw_grade');
    }

    public function getTebalListProperty()
    {
        return StokVeneerJadiModel::where('stok_lembar', '>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return (float) StokVeneerJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('nilai_stok');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) StokVeneerJadiModel::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_lembar');
    }

    public function getCoreTypeLabel($row): ?string
    {
        if ((float) $row->tebal <= 1.0) {
            return null;
        }

        if ((float) $row->panjang === 244.0 && (float) $row->lebar === 122.0) {
            return 'Long Core';
        }

        if (
            ((float) $row->panjang === 122.0 && (float) $row->lebar === 244.0)
            || ((float) $row->panjang === 122.0 && (float) $row->lebar === 130.0)
        ) {
            return 'Short Core';
        }

        return null;
    }
}
