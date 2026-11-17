<?php

namespace App\Services;

use App\Models\HasilPilihVeneer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HasilPilihVeneerService
{
    /**
     * Serah (atau serah ulang) hasil pilih veneer ke gudang.
     * Bekas penolakan gudang dikosongkan.
     */
    public function serahKeGudang(HasilPilihVeneer $record): void
    {
        if ((float) $record->jumlah <= 0) {
            throw new \Exception('Gagal: Jumlah hasil masih 0 lembar.');
        }

        $record->update([
            'diserahkan_at' => now(),
            'diserahkan_by' => Auth::id(),
            'ditolak_gudang_oleh' => null,
            'alasan_tolak_gudang' => null,
            'ditolak_gudang_at' => null,
        ]);
    }

    /**
     * Dipanggil dari sisi gudang untuk menolak hasil pilih veneer.
     * Jangan isi diterima_gudang_at, karena itu memicu mutasi stok.
     */
    public function tolakDariGudang(HasilPilihVeneer $record, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw new \Exception('Gagal: Alasan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($record, $alasan) {
            $fresh = HasilPilihVeneer::where('id', $record->id)->lockForUpdate()->first();

            if (! $fresh) {
                throw new \Exception('Gagal: Data hasil pilih veneer tidak ditemukan.');
            }

            if ($fresh->diterima_gudang_at !== null) {
                throw new \Exception('Gagal: Sudah diterima gudang, tidak bisa ditolak.');
            }

            if ($fresh->diserahkan_at === null) {
                throw new \Exception('Gagal: Hasil ini belum diserahkan ke gudang.');
            }

            $fresh->update([
                // dikosongkan supaya tombol kirim ulang muncul lagi di produksi
                'diserahkan_at' => null,
                'diserahkan_by' => null,
                'ditolak_gudang_oleh' => Auth::id(),
                'alasan_tolak_gudang' => $alasan,
                'ditolak_gudang_at' => now(),
            ]);
        });
    }
}
