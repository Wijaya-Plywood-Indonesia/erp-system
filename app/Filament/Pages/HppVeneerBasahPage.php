<?php

namespace App\Filament\Pages;

use App\Models\HppVeneerBasahLog;
use App\Models\JenisKayu;
use Filament\Pages\Page;
use UnitEnum;

class HppVeneerBasahPage extends Page
{
    protected string $view = 'filament.pages.hpp-veneer-basah-page';

    protected static ?string $navigationLabel = 'Log HPP Veneer Basah';
<<<<<<< HEAD

    protected static string|UnitEnum|null $navigationGroup = 'HPP';

    protected static ?string $title = 'Log HPP Veneer Basah';
    protected static ?int $navigationSort = 20;
=======
    protected static string|UnitEnum|null $navigationGroup = 'HPP';
    protected static ?string $title          = 'Log HPP Veneer Basah';
    protected static ?int    $navigationSort = 20;
>>>>>>> develop

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang = '';
    public string $filterLebar = '';
    public string $filterTebal = '';

    // ── Computed: log transaksi ────────────────────────────────
    public function getLogsProperty()
    {
        return HppVeneerBasahLog::with('jenisKayu')
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterLebar, fn($q) => $q->where('lebar', $this->filterLebar))
            ->when($this->filterTebal, fn($q) => $q->where('tebal', $this->filterTebal))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    // ── Computed: ukuran unik untuk filter ────────────────────
    public function getUkuranListProperty()
    {
        return HppVeneerBasahLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }
}
