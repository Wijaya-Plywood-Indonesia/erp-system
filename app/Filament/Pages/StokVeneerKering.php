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
    // Ambil snapshot m3/hpp/nilai dari baris terakhir per kombinasi
    $latest = ModelStok::with(['ukuran', 'jenisKayu'])
        ->select('stok_veneer_kerings.*')
        ->join(DB::raw('(SELECT MAX(id) as max_id FROM stok_veneer_kerings GROUP BY id_ukuran, id_jenis_kayu, kw) as latest'), function ($join) {
            $join->on('stok_veneer_kerings.id', '=', 'latest.max_id');
        })
        ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
        ->where('stok_m3_sesudah', '>', 0)
        ->get();

    // Hitung total lembar (masuk - keluar) per kombinasi lalu inject ke collection
    return $latest->map(function ($row) {
        $masuk = ModelStok::where('id_ukuran', $row->id_ukuran)
            ->where('id_jenis_kayu', $row->id_jenis_kayu)
            ->where('kw', $row->kw)
            ->where('jenis_transaksi', 'masuk')
            ->sum('qty');

        $keluar = ModelStok::where('id_ukuran', $row->id_ukuran)
            ->where('id_jenis_kayu', $row->id_jenis_kayu)
            ->where('kw', $row->kw)
            ->where('jenis_transaksi', 'keluar')
            ->sum('qty');

        $row->total_lembar = (int) ($masuk - $keluar);
        return $row;
    });
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