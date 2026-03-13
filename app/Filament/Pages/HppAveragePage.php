<?php

namespace App\Filament\Pages;

use App\Models\HppAverageLog;
use Filament\Pages\Page;
use UnitEnum;

class HppAveragePage extends Page
{
    protected string $view = 'filament.pages.hpp-average-page';

    protected static ?string $navigationLabel = 'HPP Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Hpp';
    protected static ?string $title = 'Ringkasan HPP Average';
    protected static ?int $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterPanjang   = '';
    public string $filterJenisKayu = '';

    // ── Computed: log transaksi ascending (buku besar) ─────────
    public function getLogsProperty()
    {
        return HppAverageLog::with('jenisKayu')
            ->whereNull('grade')
            ->when($this->filterPanjang,   fn($q) => $q->where('panjang',       $this->filterPanjang))
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }
}
