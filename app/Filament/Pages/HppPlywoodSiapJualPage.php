<?php

namespace App\Filament\Pages;

use App\Models\HppPlywoodSiapJualLog;
use App\Models\JenisKayu;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class HppPlywoodSiapJualPage extends Page
{
    use HasPageShield;
    protected string $view = 'filament.pages.hpp-plywood-siap-jual-page';

    protected static ?string $navigationLabel = 'Log Plywood Siap Jual';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title          = 'Log Plywood Siap Jual';
    protected static ?int    $navigationSort = 18;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterPanjang   = '';
    public string $filterLebar     = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    // ── Computed: log transaksi ────────────────────────────────
    public function getLogsProperty()
    {
        return HppPlywoodSiapJualLog::with('jenisKayu')
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
        return HppPlywoodSiapJualLog::select('panjang', 'lebar', 'tebal')
            ->distinct()
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }
}