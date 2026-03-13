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
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Basah';
    protected static ?int    $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';

    // ── Computed: semua summaries ──────────────────────────────
    public function getSummariesProperty()
    {
        return HppVeneerBasahSummary::with('jenisKayu')
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal', $this->filterTebal))
            ->where('stok_lembar', '>', 0)
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }

    // ── Computed: grouped per tebal (F/B vs Core implisit) ────
    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
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
            ->sum('nilai_stok');
    }

    // ── Computed: total lembar keseluruhan ─────────────────────
    public function getTotalLembarProperty(): int
    {
        return (int) HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal', $this->filterTebal))
            ->sum('stok_lembar');
    }
}