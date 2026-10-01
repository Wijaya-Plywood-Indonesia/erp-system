<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\Pegawai;
use App\Models\PegawaiRotary;
use App\Models\ProduksiRotary;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RotaryDashboardSource implements DashboardSourceInterface
{
    public function getLabel(): string
    {
        return 'Rotary';
    }

    public function canAccess(User $user): bool
    {
        return true; // Sesuaikan dengan role/permission jika perlu
    }

    /**
     * Total lembar veneer basah dari detail_hasil_palet_rotaries
     * berdasarkan tgl_produksi di tabel produksi_rotaries (induk).
     */
    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_hasil_palet_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->sum('detail_hasil_palet_rotaries.total_lembar');

        // Detail per kualitas (kw)
        $detail = DB::table('detail_hasil_palet_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'detail_hasil_palet_rotaries.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->selectRaw('kw, SUM(total_lembar) as jumlah')
            ->groupBy('kw')
            ->get()
            ->map(fn ($row) => ['nama' => 'Kw ' . $row->kw, 'jumlah' => $row->jumlah])
            ->toArray();

        return [
            'total'  => $total,
            'satuan' => 'Lembar',
            'detail' => $detail,
        ];
    }

    /**
     * Serah terima Rotary menggunakan pivot tabel.
     * Filter tipe = 'rotary' agar tidak double count.
     * (1 palet bisa punya 2 baris pivot: 'rotary' dan 'gudang_veneer_basah')
     */
    public function getSerahTerima(string $tanggal): array
    {
        $query = DB::table('detail_hasil_palet_rotary_serah_terima_pivot as pivot')
            ->join('detail_hasil_palet_rotaries as dhpr', 'dhpr.id', '=', 'pivot.id_detail_hasil_palet_rotary')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'dhpr.id_produksi')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->where('pivot.tipe', 'rotary'); // hanya 1 tipe agar tidak kena double count

        $totalLembar = $query->sum('dhpr.total_lembar');
        $totalPalet  = $query->distinct('pivot.id_detail_hasil_palet_rotary')
                             ->count('pivot.id_detail_hasil_palet_rotary');

        return [
            'total'  => $totalLembar,
            'satuan' => 'Lembar',
            'detail' => [
                ['nama' => 'Jumlah Palet Diserah', 'jumlah' => $totalPalet],
            ],
        ];
    }

    /**
     * Pegawai dari tabel pegawai_rotaries.
     * Jam masuk diambil langsung dari field jam_masuk di tabel tersebut.
     */
    public function getPegawai(string $tanggal): array
    {
        $rows = DB::table('pegawai_rotaries')
            ->join('produksi_rotaries', 'produksi_rotaries.id', '=', 'pegawai_rotaries.id_produksi')
            ->join('pegawais', 'pegawais.id', '=', 'pegawai_rotaries.id_pegawai')
            ->whereDate('produksi_rotaries.tgl_produksi', $tanggal)
            ->whereNotNull('pegawai_rotaries.id_pegawai')
            ->select(
                'pegawais.nama_pegawai',
                'pegawais.karyawan_di',
                'pegawai_rotaries.jam_masuk',
                'pegawai_rotaries.jam_pulang',
                'pegawai_rotaries.role',
                'pegawai_rotaries.id_pegawai',
            )
            ->distinct()
            ->get();

        $list = $rows->map(function ($row) {
            $jam = $row->jam_masuk ?? null;
            $shift = '-';
            if ($jam) {
                $hour = (int) explode(':', $jam)[0];
                $shift = ($hour >= 6 && $hour < 17) ? 'Pagi' : 'Malam';
            }

            return [
                'nama'      => $row->nama_pegawai,
                'shift'     => $shift,
                'jam_masuk' => $jam ?? '-',
                'role'      => $row->role ?? '-',
            ];
        })->values()->toArray();

        return [
            'total' => $rows->pluck('id_pegawai')->unique()->count(),
            'list'  => $list,
        ];
    }
}
