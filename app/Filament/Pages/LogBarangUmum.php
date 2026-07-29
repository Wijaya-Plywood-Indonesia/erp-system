<?php

namespace App\Filament\Pages;

use App\Models\BarangUmum;
use App\Models\LogBarangUmum as LogBarangUmumModel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class LogBarangUmum extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.log-barang-umum';

    protected static ?string $navigationLabel = 'Log Barang Umum';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log Barang Umum';
    protected static ?int    $navigationSort = 31;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // ── State filter ──────────────────────────────────────────
    public string $filterBarang = '';
    public string $filterTipe   = '';

    public function getLogsProperty()
    {
        return LogBarangUmumModel::with('barangUmum')
            ->when($this->filterBarang, fn($q) => $q->where('id_barang_umum', $this->filterBarang))
            ->when($this->filterTipe,   fn($q) => $q->where('tipe_transaksi', $this->filterTipe))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    public function getBarangListProperty()
    {
        return BarangUmum::orderBy('nama_barang')->pluck('nama_barang', 'id');
    }
}