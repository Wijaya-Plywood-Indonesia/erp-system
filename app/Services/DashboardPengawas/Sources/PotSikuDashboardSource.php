<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class PotSikuDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Pot Siku';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_barang_dikerjakan_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->sum('detail_barang_dikerjakan_pot_siku.tinggi');

        $detail = DB::table('detail_barang_dikerjakan_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'detail_barang_dikerjakan_pot_siku.id_produksi_pot_siku')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->selectRaw('detail_barang_dikerjakan_pot_siku.kw, SUM(detail_barang_dikerjakan_pot_siku.tinggi) as jumlah, COUNT(*) as palet')
            ->groupBy('detail_barang_dikerjakan_pot_siku.kw')
            ->get()
            ->map(fn ($row) => [
                'nama' => 'Kw '.$row->kw,
                'jumlah' => $row->jumlah,
            ])
            ->toArray();

        return [
            'total' => $total,
            'satuan' => 'cm',
            'detail' => $detail,
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        return ['total' => 0, 'satuan' => '-', 'detail' => []];
    }

    public function getPegawai(string $tanggal): array
    {
        $rows = DB::table('pegawai_pot_siku')
            ->join('produksi_pot_siku', 'produksi_pot_siku.id', '=', 'pegawai_pot_siku.id_produksi_pot_siku')
            ->join('pegawais', 'pegawais.id', '=', 'pegawai_pot_siku.id_pegawai')
            ->whereDate('produksi_pot_siku.tanggal_produksi', $tanggal)
            ->whereNotNull('pegawai_pot_siku.id_pegawai')
            ->select('pegawais.kode_pegawai', 'pegawai_pot_siku.id_pegawai')
            ->distinct()
            ->get();

        $kodePegawaiList = $rows->pluck('kode_pegawai')->filter()->unique()->values()->toArray();

        return [
            'total' => $rows->pluck('id_pegawai')->unique()->count(),
            'list' => $kodePegawaiList,
            'filter_absen' => true,
        ];
    }
}
