<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class PressDryerDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Press Dryer';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_hasils')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->sum('detail_hasils.isi');

        $detail = DB::table('detail_hasils')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->selectRaw('detail_hasils.kw, SUM(detail_hasils.isi) as jumlah, COUNT(*) as palet')
            ->groupBy('detail_hasils.kw')
            ->get()
            ->map(fn ($row) => [
                'nama' => 'Kw '.$row->kw,
                'jumlah' => $row->jumlah,
            ])
            ->toArray();

        return [
            'total' => $total,
            'satuan' => 'Lembar',
            'detail' => $detail,
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        $query = DB::table('serah_terima_veneer_kering')
            ->join('detail_hasils', 'detail_hasils.id', '=', 'serah_terima_veneer_kering.id_detail_hasil')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->where('serah_terima_veneer_kering.tipe_sumber', 'dryer');

        $totalLembar = (clone $query)->sum('detail_hasils.isi');
        $totalPalet = (clone $query)->count('serah_terima_veneer_kering.id');

        return [
            'total' => $totalLembar,
            'satuan' => 'Lembar',
            'detail' => [
                ['nama' => 'Jumlah Palet Diserah', 'jumlah' => $totalPalet],
            ],
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        $rows = DB::table('detail_pegawais')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_pegawais.id_produksi_dryer')
            ->join('pegawais', 'pegawais.id', '=', 'detail_pegawais.id_pegawai')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->whereNotNull('detail_pegawais.id_pegawai')
            ->select('pegawais.kode_pegawai', 'detail_pegawais.id_pegawai')
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
