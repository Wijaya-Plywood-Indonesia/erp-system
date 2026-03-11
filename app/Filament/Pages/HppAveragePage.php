<?php

namespace App\Filament\Pages;

use App\Models\HppAverageLog;
use App\Models\JenisKayu;
use App\Services\HppAverageService;
use Filament\Pages\Page;
use UnitEnum;

class HppAveragePage extends Page
{
    protected string $view = 'filament.pages.hpp-average-page';

    protected static ?string $navigationLabel = 'Log HPP Average';
    protected static string|UnitEnum|null $navigationGroup = 'HPP Average';
    protected static ?string $title          = 'Log HPP Average';
    protected static ?int    $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterPanjang    = '';
    public string $filterJenisKayu  = '';
    public string $logGrade         = 'A';   // sub-tab: 'A' | 'B'

    // ── Computed: Log transaksi (ascending — buku besar) ───────
    public function getLogsProperty()
    {
        return HppAverageLog::with('jenisKayu')
            ->when($this->filterPanjang,   fn($q) => $q->where('panjang',       $this->filterPanjang))
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    // ── Computed: Badge count per grade (ikut filter) ──────────
    public function getLogCountsProperty(): array
    {
        $base = HppAverageLog::query()
            ->when($this->filterPanjang,   fn($q) => $q->where('panjang',       $this->filterPanjang))
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu));

        return [
            'A' => (clone $base)->where('grade', 'A')->count(),
            'B' => (clone $base)->where('grade', 'B')->count(),
        ];
    }
}
