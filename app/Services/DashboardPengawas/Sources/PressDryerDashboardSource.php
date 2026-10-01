<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use Illuminate\Support\Facades\DB;

class PressDryerDashboardSource implements DashboardSourceInterface
{
    public function getLabel(): string
    {
        return 'Press Dryer';
    }

    public function canAccess(User $user): bool
    {
        return true; // Sesuaikan dengan role/permission jika perlu
    }

    /**
     * Total produksi veneer kering dari detail_hasils.
     * Induk: produksi_press_dryers, kolom tanggal: tanggal_produksi.
     * Kolom kuantitas: isi (per palet).
     */
    public function getProduksi(string $tanggal): array
    {
        $total = DB::table('detail_hasils')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->sum('detail_hasils.isi');

        // Detail breakdown per kualitas (kw)
        $detail = DB::table('detail_hasils')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->selectRaw('detail_hasils.kw, SUM(detail_hasils.isi) as jumlah, COUNT(*) as palet')
            ->groupBy('detail_hasils.kw')
            ->get()
            ->map(fn ($row) => [
                'nama'   => 'Kw ' . $row->kw,
                'jumlah' => $row->jumlah,
            ])
            ->toArray();

        return [
            'total'  => $total,
            'satuan' => 'Lembar',
            'detail' => $detail,
        ];
    }

    /**
     * Serah terima veneer kering dari Press Dryer.
     * Menggunakan tabel serah_terima_veneer_kering, filter tipe_sumber = 'dryer'.
     * Jumlah dihitung dari detail_hasils.isi (kuantitas palet asal).
     */
    public function getSerahTerima(string $tanggal): array
    {
        $query = DB::table('serah_terima_veneer_kering')
            ->join('detail_hasils', 'detail_hasils.id', '=', 'serah_terima_veneer_kering.id_detail_hasil')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_hasils.id_produksi_dryer')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->where('serah_terima_veneer_kering.tipe_sumber', 'dryer');

        $totalLembar = $query->sum('detail_hasils.isi');
        $totalPalet  = $query->count('serah_terima_veneer_kering.id');

        return [
            'total'  => $totalLembar,
            'satuan' => 'Lembar',
            'detail' => [
                ['nama' => 'Jumlah Palet Diserah', 'jumlah' => $totalPalet],
            ],
        ];
    }

    /**
     * Pegawai dari detail_pegawais.
     * Jam masuk diambil dari field `masuk` di tabel tersebut.
     */
    public function getPegawai(string $tanggal): array
    {
        $rows = DB::table('detail_pegawais')
            ->join('produksi_press_dryers', 'produksi_press_dryers.id', '=', 'detail_pegawais.id_produksi_dryer')
            ->join('pegawais', 'pegawais.id', '=', 'detail_pegawais.id_pegawai')
            ->whereDate('produksi_press_dryers.tanggal_produksi', $tanggal)
            ->whereNotNull('detail_pegawais.id_pegawai')
            ->select(
                'pegawais.nama_pegawai',
                'pegawais.karyawan_di',
                'detail_pegawais.masuk as jam_masuk',
                'detail_pegawais.tugas',
                'detail_pegawais.id_pegawai',
            )
            ->get();

        // Deduplikasi: 1 pegawai bisa muncul di 2 shift (pagi & malam)
        // Ambil yang jam_masuk-nya paling awal
        $grouped = $rows->groupBy('id_pegawai')->map(function ($items) {
            return $items->sortBy('jam_masuk')->first();
        })->values();

        $list = $grouped->map(function ($row) {
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
                'role'      => $row->tugas ?? '-',
            ];
        })->toArray();

        return [
            'total' => $grouped->count(),
            'list'  => $list,
        ];
    }
}
