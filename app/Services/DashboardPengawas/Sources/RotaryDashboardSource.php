<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class RotaryDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Rotary';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_hasil_palet_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->sum('detail_hasil_palet_rotaries.total_lembar');

        $detail = DB::table('detail_hasil_palet_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->selectRaw('kw, SUM(total_lembar) as jumlah')
            ->groupBy('kw')
            ->get()
            ->map(fn ($row) => ['nama' => 'Kw '.$row->kw, 'jumlah' => $row->jumlah])
            ->toArray();

        return [
            'total' => $total,
            'satuan' => 'Lembar',
            'detail' => $detail,
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        $query = DB::table('detail_hasil_palet_rotary_serah_terima_pivot as pivot')
            ->join('detail_hasil_palet_rotaries as dhpr', 'dhpr.id', '=', 'pivot.id_detail_hasil_palet_rotary')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'dhpr.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->where('pivot.tipe', 'rotary');

        $totalLembar = $query->sum('dhpr.total_lembar');
        $totalPalet = $query->distinct('pivot.id_detail_hasil_palet_rotary')
            ->count('pivot.id_detail_hasil_palet_rotary');

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
        $rows = DB::table('pegawai_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'pegawai_rotaries.id_produksi')
            ->join('pegawais', 'pegawais.id', '=', 'pegawai_rotaries.id_pegawai')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->whereNotNull('pegawai_rotaries.id_pegawai')
            ->select('pegawais.kode_pegawai', 'pegawai_rotaries.id_pegawai')
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
