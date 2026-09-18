<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class RekapStokVeneer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected string $view = 'filament.pages.rekap-stok-veneer';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Rekap Stok Veneer';
    protected static ?string $navigationLabel = 'Rekap Stok Veneer';
    protected static ?int $navigationSort = 20;

    public string $search = '';
    public string $filterKayu = '';
    public string $filterKw = '';

    public function mount(): void {}

    protected function buildAllStocks(): array
    {
        $allStocks = [];
        $jenisKayus = \App\Models\JenisKayu::pluck('nama_kayu', 'id')->toArray();

        $getGroupKey = fn($idJk, $p, $l, $t) =>
            $idJk . '_' . (float)$p . 'x' . (float)$l . 'x' . (float)$t;

        $initGroup = function ($key, $idJk, $p, $l, $t) use (&$allStocks, $jenisKayus) {
            if (!isset($allStocks[$key])) {
                $namaKayu = $jenisKayus[$idJk] ?? 'Unknown';
                $allStocks[$key] = [
                    'title'        => (float)$p . ' × ' . (float)$l . ' × ' . (float)$t . ' mm — ' . $namaKayu,
                    'ukuran'       => (float)$p . 'x' . (float)$l . 'x' . (float)$t,
                    'jenis_kayu'   => $namaKayu,
                    'panjang'      => (float)$p,
                    'lebar'        => (float)$l,
                    'tebal'        => (float)$t,
                    'wijayaBasah'  => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                    'wijayaKering' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                    'wijayaJadi'   => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                ];
            }
        };

        $normalizeKw = function ($raw) {
            $kw = strtoupper(trim((string)$raw));
            if (!in_array($kw, ['1', '2', '3', '4', '5', 'AF'])) return null;
            return is_numeric($kw) ? (int)$kw : $kw;
        };

        // 1. Basah
        foreach (\App\Models\HppVeneerBasahSummary::all() as $b) {
            $kw = $normalizeKw($b->kw);
            if ($kw === null) continue;
            $key = $getGroupKey($b->id_jenis_kayu, $b->panjang, $b->lebar, $b->tebal);
            $initGroup($key, $b->id_jenis_kayu, $b->panjang, $b->lebar, $b->tebal);
            $allStocks[$key]['wijayaBasah'][$kw] += $b->stok_lembar;
        }

        // 2. Jadi
        foreach (\App\Models\StokVeneerJadi::all() as $j) {
            $kw = $normalizeKw($j->kw_grade);
            if ($kw === null) continue;
            $key = $getGroupKey($j->id_jenis_kayu, $j->panjang, $j->lebar, $j->tebal);
            $initGroup($key, $j->id_jenis_kayu, $j->panjang, $j->lebar, $j->tebal);
            $allStocks[$key]['wijayaJadi'][$kw] += $j->stok_lembar;
        }

        // 3. Kering
        $ukurans  = \App\Models\Ukuran::all()->keyBy('id');
        $keringIds = DB::table('stok_veneer_kerings')
            ->select(DB::raw('MAX(id) as max_id'))
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->pluck('max_id');

        foreach (\App\Models\StokVeneerKering::whereIn('id', $keringIds)->get() as $k) {
            $kw     = $normalizeKw($k->kw);
            if ($kw === null) continue;
            $ukuran = $ukurans->get($k->id_ukuran);
            if (!$ukuran) continue;
            $key = $getGroupKey($k->id_jenis_kayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $initGroup($key, $k->id_jenis_kayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $allStocks[$key]['wijayaKering'][$kw] += $k->stok_lembar_sesudah;
        }

        // Hapus ukuran yang total stoknya = 0
        $allStocks = array_filter($allStocks, function ($g) {
            foreach (['wijayaBasah', 'wijayaKering', 'wijayaJadi'] as $cat) {
                foreach ($g[$cat] as $v) {
                    if ($v != 0) return true;
                }
            }
            return false;
        });

        usort($allStocks, fn($a, $b) => strcmp($a['title'], $b['title']));

        return array_values($allStocks);
    }

    protected function getViewData(): array
    {
        $allStocks = $this->buildAllStocks();

        // Opsi jenis kayu untuk filter dropdown
        $kayuOptions = collect($allStocks)
            ->pluck('jenis_kayu')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Terapkan filter
        if ($this->filterKayu !== '') {
            $allStocks = array_values(array_filter($allStocks,
                fn($g) => strtolower($g['jenis_kayu']) === strtolower($this->filterKayu)
            ));
        }

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $allStocks = array_values(array_filter($allStocks,
                fn($g) => str_contains(strtolower($g['title']), $q)
            ));
        }

        if ($this->filterKw !== '') {
            $filterKw = $this->filterKw;
            $kw = is_numeric($filterKw) ? (int)$filterKw : strtoupper($filterKw);
            $allStocks = array_values(array_filter($allStocks, function ($g) use ($kw) {
                $basah  = $g['wijayaBasah'][$kw]  ?? 0;
                $kering = $g['wijayaKering'][$kw] ?? 0;
                $jadi   = $g['wijayaJadi'][$kw]   ?? 0;
                return ($basah + $kering + $jadi) != 0;
            }));
        }

        return compact('allStocks', 'kayuOptions');
    }
}
