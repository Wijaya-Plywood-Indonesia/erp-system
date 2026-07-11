<?php

namespace App\Filament\Pages;

use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class HppTriplekJadiPage extends Page
{
    use HasPageShield;
    protected string $view = 'filament.pages.hpp-triplek-jadi-page';

    protected static ?string $navigationLabel = 'Log HPP Triplek Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log HPP Triplek Jadi';
    protected static ?int    $navigationSort = 22;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang   = '';
    public string $filterLebar     = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    // ── Computed: log transaksi ────────────────────────────────
    public function getLogsProperty()
    {
        return HppTriplekJadiLog::with('jenisKayu')
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterPanjang,   fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterLebar,     fn($q) => $q->where('lebar',   $this->filterLebar))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',   $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw_grade', $this->filterKw))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    // ── Computed: ukuran unik untuk filter ────────────────────
    public function getUkuranListProperty()
    {
        return HppTriplekJadiLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }
}