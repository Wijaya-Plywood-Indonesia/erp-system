<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal2;
use App\Models\JurnalTiga;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Jurnal2ToJurnal3Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil baris Jurnal 2 yang belum disinkronkan
                $rows = Jurnal2::whereRaw('LOWER(status_sinkron) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal2ToJurnal3: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    /**
                     * 2. TENTUKAN HIERARKI AKUN
                     * Mencari Akun Seratus (Induk) dari Jurnal 2 (Misal 1110 -> 1100)
                     */
                    $akun = AnakAkun::where('kode_anak_akun', $row->no_akun)->first();
                    if (!$akun) {
                        Log::warning("Jurnal2 ID {$row->id}: Akun {$row->no_akun} tidak ditemukan di master.");
                        continue;
                    }

                    // Menentukan Akun Seratus (1100, 1200, 1300, dsb)
                    $akunSeratus = $akun->parentAkun ?? $akun;
                    $indukAkun = $akunSeratus->indukAkun; // Level 1000

                    if (!$indukAkun) {
                        Log::warning("Jurnal2 ID {$row->id}: Induk Akun 1000 tidak ditemukan.");
                        continue;
                    }

                    $kodeSeratus = $akunSeratus->kode_anak_akun;

                    // Ambil nilai dari Jurnal 2
                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->kubikasi;

                    // 3. TARGET: JURNAL TIGA (Update or Create)
                    $jurnal3 = JurnalTiga::where('akun_seratus', $kodeSeratus)->first();

                    if ($jurnal3) {
                        // Akumulasi Saldo Net
                        $newTotal  = (float) $jurnal3->total    + $addTotal;
                        $newBanyak = (float) $jurnal3->banyak   + $addBanyak;
                        $newM3     = (float) $jurnal3->kubikasi + $addM3;

                        // HARGA: Tetap Konsisten Total / Banyak (AE / AB)
                        $newHarga = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal3->harga;

                        $jurnal3->update([
                            'total'    => $newTotal,
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'harga'    => $newHarga,
                            'status'   => 'belum sinkron', // Untuk diproses ke Neraca
                        ]);
                    } else {
                        // Jika Akun Seratus belum ada di Jurnal 3
                        $initHarga = ($addBanyak != 0)
                            ? ($addTotal / $addBanyak)
                            : (float) $row->harga;

                        JurnalTiga::create([
                            'modif1000'    => $indukAkun->kode_induk_akun,
                            'akun_seratus' => $kodeSeratus,
                            'detail'       => $akunSeratus->nama_anak_akun,
                            'banyak'       => $addBanyak,
                            'kubikasi'     => $addM3,
                            'total'        => $addTotal,
                            'harga'        => $initHarga,
                            'createdBy'    => $userName,
                            'status'       => 'belum sinkron',
                        ]);
                    }

                    // 4. Update status Jurnal 2
                    $row->update([
                        'status_sinkron' => 'sudah sinkron',
                        'synced_at'      => now(),
                        'synced_by'      => $userName,
                    ]);

                    $totalProcessed++;
                }

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Sinkronisasi Jurnal 2 ke Jurnal 3: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
