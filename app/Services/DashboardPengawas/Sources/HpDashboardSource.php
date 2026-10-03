<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\SerahTerimaHp;
use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class HpDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Hotpress';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('platform_hasil_hp')
            ->whereDate('created_at', $tanggal)
            ->sum('isi');

        $totalTriplek = DB::table('triplek_hasil_hp')
            ->whereDate('created_at', $tanggal)
            ->sum('isi');

        return [
            'total' => $total + $totalTriplek,
            'satuan' => 'Lembar',
            'detail' => [
                ['nama' => 'Platform', 'jumlah' => $total],
                ['nama' => 'Triplek', 'jumlah' => $totalTriplek],
            ],
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        // Pakai model supaya accessor getJumlahAttribute() berjalan
        $records = SerahTerimaHp::whereDate('created_at', $tanggal)->get();

        return [
            'total' => $records->sum('jumlah'),
            'satuan' => 'Lembar',
            'detail' => [],
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        $rows = DB::table('detail_pegawai_hp')
            ->join('pegawais', 'pegawais.id', '=', 'detail_pegawai_hp.id_pegawai')
            ->whereDate('detail_pegawai_hp.created_at', $tanggal)
            ->whereNotNull('detail_pegawai_hp.id_pegawai')
            ->select('pegawais.kode_pegawai', 'detail_pegawai_hp.id_pegawai')
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
