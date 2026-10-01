<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use Illuminate\Support\Facades\DB;
use App\Models\DetailBarangDikerjakanPotSiku;
use App\Models\PegawaiPotSiku;

class PotSikuDashboardSource implements DashboardSourceInterface
{
    public function getLabel(): string
    {
        return 'Pot Siku';
    }

    public function canAccess(User $user): bool
    {
        return true; 
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_barang_dikerjakan_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->sum('detail_barang_dikerjakan_pot_siku.tinggi');

        // Detail breakdown per kualitas (kw)
        $detail = DB::table('detail_barang_dikerjakan_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->selectRaw('detail_barang_dikerjakan_pot_siku.kw, SUM(detail_barang_dikerjakan_pot_siku.tinggi) as jumlah, COUNT(*) as palet')
            ->groupBy('detail_barang_dikerjakan_pot_siku.kw')
            ->get()
            ->map(fn ($row) => [
                'nama'   => 'Kw ' . $row->kw,
                'jumlah' => $row->jumlah,
            ])
            ->toArray();

        return [
            'total'  => $total,
            'satuan' => 'cm', // Tinggi is usually in cm
            'detail' => $detail,
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        // Currently no specific serah_terima table for Pot Siku is identified.
        return [
            'total'  => 0,
            'satuan' => '-',
            'detail' => [],
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        // Pegawai is handled by NewRekapAbsensiPegawaiService in the dashboard view, 
        // but we need to return the total count for the summary cards.
        $rows = DB::table('pegawai_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'pegawai_pot_siku.id_produksi_pot_siku')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->whereNotNull('pegawai_pot_siku.id_pegawai')
            ->select('pegawai_pot_siku.id_pegawai')
            ->distinct()
            ->get();

        return [
            'total' => $rows->count(),
            'list'  => []
        ];
    }
}
