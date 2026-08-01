<?php

namespace App\Filament\Pages;

use App\Models\StokLogCore as ModelsStokLogCore;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class StokLogCore extends Page
{
    use HasPageShield;
    protected static bool $shouldRegisterNavigation = true;
    protected string $view = 'filament.pages.stok-log-core';

    protected static ?string $navigationLabel = 'Stok LogCore';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title = 'Stok LogCore';
    protected static ?int $navigationSort = 2;

    public string $filterPanjang = '';
    public string $filterJenis = '';

    // ── Computed: semua stok berjalan ──────────────────
    public function getSummariesProperty()
    {
        return ModelsStokLogCore::with('jenisKayu')
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterJenis, fn($q) => $q->whereHas('jenisKayu', fn($q2) => $q2->where('nama_kayu', $this->filterJenis)))
            ->where('stok_qty', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('panjang')->sortKeys();
    }

    public function getPanjangListProperty()
    {
        return StokLogCore::where('stok_qty', '>', 0)
            ->distinct()->orderBy('panjang')->pluck('panjang');
    }

    public function getJenisListProperty()
    {
        return StokLogCore::with('jenisKayu')
            ->where('stok_qty', '>', 0)
            ->get()
            ->pluck('jenisKayu.nama_kayu')
            ->filter()->unique()->sort()->values();
    }
}
