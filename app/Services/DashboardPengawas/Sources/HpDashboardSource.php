<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use Illuminate\Support\Facades\DB;
use App\Models\Pegawai;

class HpDashboardSource implements DashboardSourceInterface
{
    public function getLabel(): string
    {
        return "Hot Press";
    }

    public function canAccess(User $user): bool
    {
        // Contoh implementasi: hanya admin atau pengawas spesifik
        // Sesuaikan dengan role di sistem Anda (misal Spatie Permission)
        // return $user->hasRole('Pengawas Hot Press') || $user->hasRole('Super Admin');
        
        return true; // Sementara di-allow semua untuk testing
    }

    public function getProduksi(string $tanggal): array
    {
        // Menghitung dari tabel platform_hasil_hp berdasarkan tanggal
        $total = DB::table('platform_hasil_hp')
            ->whereDate('created_at', $tanggal)
            ->sum('isi');

        $totalTriplek = DB::table('triplek_hasil_hp')
            ->whereDate('created_at', $tanggal)
            ->sum('isi');

        return [
            'total'  => $total + $totalTriplek,
            'satuan' => 'Lembar',
            'detail' => [
                ['nama' => 'Platform', 'jumlah' => $total],
                ['nama' => 'Triplek', 'jumlah' => $totalTriplek],
            ]
        ];
    }

    public function getSerahTerima(string $tanggal): array
    {
        // Menggunakan model agar accessor getJumlahAttribute() berjalan
        $records = \App\Models\SerahTerimaHp::whereDate('created_at', $tanggal)->get();
        $total = $records->sum('jumlah');

        return [
            'total'  => $total,
            'satuan' => 'Lembar',
            'detail' => []
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        // Query ke tabel detail_pegawai_hp untuk mencari siapa saja yang bekerja di tanggal tersebut
        $pegawaiIds = DB::table('detail_pegawai_hp')
            ->whereDate('created_at', $tanggal)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->toArray();

        $listPegawai = Pegawai::whereIn('id', $pegawaiIds)
            ->select('id', 'nama_pegawai', 'karyawan_di')
            ->get()
            ->map(function ($p) {
                return [
                    'nama'      => $p->nama_pegawai,
                    'shift'     => '-', // Belum ada data jam_masuk, bisa di-join dengan absen finger jika diperlukan
                    'jam_masuk' => '-',
                ];
            })
            ->toArray();

        return [
            'total' => count($listPegawai),
            'list'  => $listPegawai,
        ];
    }
}
