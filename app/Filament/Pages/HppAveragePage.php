<?php

namespace App\Filament\Pages;

use App\Models\HppAverageLog;
use Filament\Pages\Page;
use UnitEnum;

class HppAveragePage extends Page
{
    protected string $view = 'filament.pages.hpp-average-page';
    protected static ?string $navigationLabel = 'Log HPP Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title = 'Log HPP Kayu';
    protected static ?int $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterPanjang = '';
    public string $filterJenisKayu = '';
    public string $filterLahan = '';

    // ── Computed: log transaksi ascending (buku besar) ─────────
    public function getLogsProperty()
    {
        return HppAverageLog::with(['jenisKayu', 'lahan']) // Muat relasi lahan
            ->whereNull('grade')
            ->when($this->filterLahan, fn($q) => $q->where('id_lahan', $this->filterLahan)) // Tambahkan ini
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }
}
