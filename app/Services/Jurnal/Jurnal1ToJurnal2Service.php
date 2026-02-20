<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal1st;
use App\Models\Jurnal2;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Jurnal1ToJurnal2Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil baris Jurnal 1 yang belum disinkronkan ke Jurnal 2
                $rows = Jurnal1st::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal1ToJurnal2: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    // Identifikasi Akun Induk (Level Ratusan, misal 1111.00 -> 1110)
                    $akunInduk = $this->resolveAkunInduk((string) $row->no_akun);

                    // Ambil nilai dari Jurnal 1 (Sudah berupa nilai Net Signed dari Jurnal Umum)
                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->m3;

                    // 2. TARGET: JURNAL 2 (Update or Create)
                    $jurnal2 = Jurnal2::where('no_akun', $akunInduk)->first();

                    if ($jurnal2) {
                        // Akumulasi Saldo Net
                        $newTotal  = (float) $jurnal2->total  + $addTotal;
                        $newBanyak = (float) $jurnal2->banyak + $addBanyak;
                        $newM3     = (float) $jurnal2->kubikasi + $addM3;

                        // Harga: Total / Banyak (Sesuai Rumus AE/AB)
                        $newHarga = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal2->harga;

                        $jurnal2->update([
                            'total'    => $newTotal,
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'harga'    => $newHarga,
                            'status_sinkron' => 'belum sinkron', // Untuk diproses ke Jurnal 3
                        ]);
                    } else {
                        // Inisialisasi Akun di Jurnal 2 jika belum ada
                        $master = AnakAkun::where('kode_anak_akun', $akunInduk)->first();

                        $initHarga = ($addBanyak != 0)
                            ? ($addTotal / $addBanyak)
                            : (float) $row->harga;

                        Jurnal2::create([
                            'modif100'       => $akunInduk,
                            'no_akun'        => $akunInduk,
                            'nama_akun'      => $master->nama_anak_akun ?? 'AKUN INDUK ' . $akunInduk,
                            'banyak'         => $addBanyak,
                            'kubikasi'       => $addM3,
                            'total'          => $addTotal,
                            'harga'          => $initHarga,
                            'user_id'        => $userName,
                            'status_sinkron' => 'belum sinkron',
                            'synced_at'      => now(),
                            'synced_by'      => $userName,
                        ]);
                    }

                    // 3. Update Status Jurnal 1st
                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Sinkronisasi Jurnal 1 ke Jurnal 2: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Mapping No Akun ke Level Ratusan (1110, 1200, 1300)
     * Pola: Ambil 3 angka pertama lalu tambahkan 0
     */
    private function resolveAkunInduk(string $noAkun): string
    {
        $cleanNo = str_replace('.', '', $noAkun);
        return substr($cleanNo, 0, 3) . '0';
    }
}
