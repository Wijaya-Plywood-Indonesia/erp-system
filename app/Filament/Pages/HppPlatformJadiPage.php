<?php

namespace App\Filament\Pages;

use App\Models\HppPlatformJadiLog;
use App\Models\JenisBarang;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class HppPlatformJadiPage extends Page
{
    use HasPageShield;
    protected string $view = 'filament.pages.hpp-platform-jadi-page';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Log HPP Platform Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log HPP Platform Jadi';
    protected static ?int    $navigationSort = 20;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisBarang = '';
    public string $filterPanjang     = '';
    public string $filterLebar       = '';
    public string $filterTebal       = '';
    public string $filterKw          = '';

    // ── Computed: log transaksi ────────────────────────────────
    public function getLogsProperty()
    {
        return HppPlatformJadiLog::with('jenisBarang')
            ->when($this->filterJenisBarang, fn($q) => $q->where('id_jenis_barang', $this->filterJenisBarang))
            ->when($this->filterPanjang,     fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterLebar,       fn($q) => $q->where('lebar',   $this->filterLebar))
            ->when($this->filterTebal,       fn($q) => $q->where('tebal',   $this->filterTebal))
            ->when($this->filterKw,          fn($q) => $q->where('kw_grade', $this->filterKw))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    // ── Computed: ukuran unik untuk filter ────────────────────
    public function getUkuranListProperty()
    {
        return HppPlatformJadiLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }
}
