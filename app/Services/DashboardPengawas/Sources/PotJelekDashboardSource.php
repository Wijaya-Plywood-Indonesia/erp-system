<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class PotJelekDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Pot Jelek';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_barang_dikerjakan_pot_jelek')
            ->join('produksi_pot_jelek', 'produksi_pot_jelek.id', '=', 'detail_barang_dikerjakan_pot_jelek.id_produksi_pot_jelek')
            ->whereDate('produksi_pot_jelek.tanggal_produksi', $tanggal)
            ->sum('detail_barang_dikerjakan_pot_jelek.tinggi');

        $detail = DB::table('detail_barang_dikerjakan_pot_jelek')
            ->join('produksi_pot_jelek', 'produksi_pot_jelek.id', '=', 'detail_barang_dikerjakan_pot_jelek.id_produksi_pot_jelek')
            ->whereDate('produksi_pot_jelek.tanggal_produksi', $tanggal)
            ->selectRaw('detail_barang_dikerjakan_pot_jelek.kw, SUM(detail_barang_dikerjakan_pot_jelek.tinggi) as jumlah, COUNT(*) as palet')
            ->groupBy('detail_barang_dikerjakan_pot_jelek.kw')
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
        $rows = DB::table('pegawai_pot_jelek')
            ->join('produksi_pot_jelek', 'produksi_pot_jelek.id', '=', 'pegawai_pot_jelek.id_produksi_pot_jelek')
            ->join('pegawais', 'pegawais.id', '=', 'pegawai_pot_jelek.id_pegawai')
            ->whereDate('produksi_pot_jelek.tanggal_produksi', $tanggal)
            ->whereNotNull('pegawai_pot_jelek.id_pegawai')
            ->select('pegawais.kode_pegawai', 'pegawai_pot_jelek.id_pegawai')
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
