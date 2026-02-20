<?php

namespace App\Services\Jurnal;

use App\Models\IndukAkun;
use App\Models\JurnalTiga;
use App\Models\Neraca;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Jurnal3ToNeracaService
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil data Jurnal Tiga yang belum sinkron
                $rows = JurnalTiga::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal3ToNeraca: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    // Identifikasi Akun Seribu (Induk Neraca, misal 1100 -> 1000)
                    $kodeInduk = (string) $row->modif1000;

                    // Ambil nilai dari Jurnal Tiga
                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->kubikasi;

                    // 2. TARGET: NERACA (Update or Create)
                    $neraca = Neraca::where('akun_seribu', $kodeInduk)->first();

                    if ($neraca) {
                        // Akumulasi Saldo Net Terakhir
                        $newTotal  = (float) $neraca->total    + $addTotal;
                        $newBanyak = (float) $neraca->banyak   + $addBanyak;
                        $newM3     = (float) $neraca->kubikasi + $addM3;

                        // HARGA NERACA: Tetap Konsisten Total / Banyak (AE / AB)
                        $newHarga = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $neraca->harga;

                        $neraca->update([
                            'total'    => $newTotal,
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'harga'    => $newHarga,
                            'detail'   => IndukAkun::where('kode_induk_akun', $kodeInduk)->value('nama_induk_akun') ?? $neraca->detail,
                        ]);
                    } else {
                        // Jika Akun Seribu belum ada di Neraca
                        $namaInduk = IndukAkun::where('kode_induk_akun', $kodeInduk)->value('nama_induk_akun');

                        $initHarga = ($addBanyak != 0)
                            ? ($addTotal / $addBanyak)
                            : (float) $row->harga;

                        Neraca::create([
                            'akun_seribu' => $kodeInduk,
                            'detail'      => $namaInduk ?? 'INDUK ' . $kodeInduk,
                            'banyak'      => $addBanyak,
                            'kubikasi'    => $addM3,
                            'total'       => $addTotal,
                            'harga'       => $initHarga,
                        ]);
                    }

                    // 3. Update status Jurnal Tiga
                    $row->update([
                        'status'          => 'sinkron',
                        'synchronized_by' => $userName,
                        'synchronized_at' => now(),
                    ]);

                    $totalProcessed++;
                }

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Sinkronisasi Jurnal 3 ke Neraca: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
