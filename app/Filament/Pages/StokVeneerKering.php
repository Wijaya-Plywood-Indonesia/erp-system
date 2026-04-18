<?php

namespace App\Filament\Pages;

use App\Models\StokVeneerKering as ModelStok;
use App\Models\JenisKayu;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class StokVeneerKering extends Page
{
    protected string $view = 'filament.pages.stok-veneer-kering';

    protected static ?string $navigationLabel = 'Stok Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Kering';
    protected static ?int    $navigationSort = 20;

    public string $filterJenisKayu = '';

    public function getLatestStokProperty()
    {
        // Mengambil data stok TERAKHIR (snapshot) per kombinasi ukuran, jenis kayu, dan kw
        return ModelStok::with(['ukuran', 'jenisKayu'])
            ->select('stok_veneer_kerings.*')
            ->join(DB::raw('(SELECT MAX(id) as max_id FROM stok_veneer_kerings GROUP BY id_ukuran, id_jenis_kayu, kw) as latest'), function($join) {
                $join->on('stok_veneer_kerings.id', '=', 'latest.max_id');
            })
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->where('stok_m3_sesudah', '>', 0)
            ->get();
    }

    public function getGroupedStokProperty()
    {
        // Mengelompokkan berdasarkan tebal dari relasi ukuran
        return $this->latestStok->groupBy(fn($item) => (string) ($item->ukuran->tebal ?? '0'))->sortKeys();
    }

    public function getTotalM3Property(): float
    {
        return $this->latestStok->sum('stok_m3_sesudah');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return $this->latestStok->sum('nilai_stok_sesudah');
    }
}