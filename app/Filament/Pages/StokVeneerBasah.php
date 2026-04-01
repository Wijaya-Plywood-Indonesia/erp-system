<?php

namespace App\Filament\Pages;

use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use Filament\Pages\Page;
use UnitEnum;

class StokVeneerBasah extends Page
{
    protected string $view = 'filament.pages.stok-veneer-basah';

    protected static ?string $navigationLabel = 'Stok Veneer Basah';
    protected static string|UnitEnum|null $navigationGroup = 'Stok Veneer Basah';
    protected static ?string $title          = 'Stok Veneer Basah';
    protected static ?int    $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    // ── Computed: semua summaries ──────────────────────────────
    public function getSummariesProperty()
    {
        return HppVeneerBasahSummary::with('jenisKayu')
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal', $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',    $this->filterKw))
            ->where('stok_lembar', '>', 0)
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }

    // ── Computed: grouped per tebal (F/B vs Core implisit) ────
    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    // ── Computed: daftar KW unik untuk filter ─────────────────
    public function getKwListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->whereNotNull('kw')
            ->distinct()->orderBy('kw')->pluck('kw');
    }

    // ── Computed: daftar tebal unik untuk filter ───────────────
    public function getTebalListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->distinct()->orderBy('tebal')->pluck('tebal');
    }

    // ── Computed: total nilai stok keseluruhan ─────────────────
    public function getTotalNilaiStokProperty(): float
    {
        return (float) HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal', $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',    $this->filterKw))
            ->sum('nilai_stok');
    }

    // ── Computed: total lembar keseluruhan ─────────────────────
    public function getTotalLembarProperty(): int
    {
        return (int) HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal', $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',    $this->filterKw))
            ->sum('stok_lembar');
    }
}