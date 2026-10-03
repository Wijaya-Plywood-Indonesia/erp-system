<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;

class KediDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    public function getLabel(): string
    {
        return 'Kedi';
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    protected function filterTanggal($query, string $tanggal)
    {
        return $query->where(function ($q) use ($tanggal) {
            $q->whereDate('produksi_kedi.tanggal_actual_bongkar', $tanggal)
                ->orWhere(function ($s) use ($tanggal) {
                    $s->whereNull('produksi_kedi.tanggal_actual_bongkar')
                        ->whereDate('produksi_kedi.tanggal_bongkar', $tanggal);
                });
        });
    }

    public function getProduksi(string $tanggal): array
    {
        $query = DB::table('detail_bongkar_kedi')
            ->join('produksi_kedi', 'produksi_kedi.id', '=', 'detail_bongkar_kedi.id_produksi_kedi');

        $total = $this->filterTanggal($query, $tanggal)
            ->sum('detail_bongkar_kedi.jumlah');

        return [
            'total' => (int) $total,
            'satuan' => 'Lembar',
            'detail' => [],
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        $query = DB::table('serah_terima_veneer_kering as stvk')
            ->join('detail_bongkar_kedi', 'detail_bongkar_kedi.id', '=', 'stvk.id_detail_bongkar_kedi')
            ->join('produksi_kedi', 'produksi_kedi.id', '=', 'detail_bongkar_kedi.id_produksi_kedi')
            ->where('stvk.tipe_sumber', 'kedi');

        $query = $this->filterTanggal($query, $tanggal);

        $totalLembar = (int) (clone $query)->sum('detail_bongkar_kedi.jumlah');
        $totalPalet = (clone $query)->count('stvk.id');

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
        $query = DB::table('detail_pegawai_kedi')
            ->join('produksi_kedi', 'produksi_kedi.id', '=', 'detail_pegawai_kedi.id_produksi_kedi')
            ->join('pegawais', 'pegawais.id', '=', 'detail_pegawai_kedi.id_pegawai')
            ->whereNotNull('detail_pegawai_kedi.id_pegawai');

        $rows = $this->filterTanggal($query, $tanggal)
            ->select('pegawais.kode_pegawai', 'detail_pegawai_kedi.id_pegawai')
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
