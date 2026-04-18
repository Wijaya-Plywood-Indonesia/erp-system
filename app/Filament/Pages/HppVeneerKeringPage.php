<?php

namespace App\Filament\Pages;

use App\Models\StokVeneerKering; // Menggunakan ini karena menyimpan detail transaksi
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Pages\Page;
use UnitEnum;

class HppVeneerKeringPage extends Page
{
    protected string $view = 'filament.pages.hpp-veneer-kering-page';

    protected static ?string $navigationLabel = 'Log HPP Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log HPP Veneer Kering';
    protected static ?int    $navigationSort = 21;

    public string $filterJenisKayu = '';
    public string $filterPanjang   = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    public function getLogsProperty()
    {
        return StokVeneerKering::with(['ukuran', 'jenisKayu'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang,   fn($q) => $q->whereHas('ukuran', fn($u) => $u->where('panjang', $this->filterPanjang)))
            ->when($this->filterTebal,     fn($q) => $q->whereHas('ukuran', fn($u) => $u->where('tebal', $this->filterTebal)))
            ->when($this->filterKw,        fn($q) => $q->where('kw', $this->filterKw))
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->get();
    }

    public function getUkuranListProperty()
    {
        $ukuranIds = StokVeneerKering::select('id_ukuran')->distinct()->pluck('id_ukuran');
        return Ukuran::whereIn('id', $ukuranIds)
            ->select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }
}