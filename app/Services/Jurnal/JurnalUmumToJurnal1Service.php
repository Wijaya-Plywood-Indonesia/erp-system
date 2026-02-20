<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalUmumToJurnal1Service
{
    /**
     * Melakukan sinkronisasi semua akun dari Jurnal Umum ke Jurnal 1
     */
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil semua baris yang belum disinkronkan
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Sinkronisasi: Tidak ada data baru untuk diproses.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    $mapInput = strtoupper(trim((string) $row->map));

                    // Validasi dasar map (D/K)
                    if (!in_array($mapInput, ['D', 'K'])) {
                        Log::warning("Baris id={$row->id} dilewati: map '{$mapInput}' tidak valid.");
                        continue;
                    }

                    // Identifikasi Akun & Modif10
                    $noAkunStr = (string) $row->no_akun;
                    $noAkunInt = (int) explode('.', $noAkunStr)[0];
                    $modif10Value = (string) (floor($noAkunInt / 10) * 10);

                    // --- 2. KALKULASI NOMINAL (Uang - Rumus Kolom N Excel) ---
                    $nominal = $this->resolveNominal($row);
                    $signedNominal = ($mapInput === 'D') ? $nominal : -$nominal;

                    // --- 3. KALKULASI VOLUME (Banyak & M3 - Logika Database Direct) ---
                    // Menggunakan logika SUMIF: Debit (+) dan Kredit (-)
                    [$addBanyak, $addM3] = $this->resolveVolumeDirect($row, $mapInput);

                    // --- 4. UPDATE ATAU CREATE DI JURNAL 1 ---
                    $jurnal1 = Jurnal1st::where('no_akun', $noAkunStr)->first();

                    if ($jurnal1) {
                        // Akumulasi Saldo Net
                        $newTotal  = (float) $jurnal1->total  + $signedNominal;
                        $newBanyak = (float) $jurnal1->banyak + $addBanyak;
                        $newM3     = (float) $jurnal1->m3     + $addM3;

                        // Perbarui Harga: AE / AB (Total Net / Banyak Net)
                        // Proteksi pembagian nol: jika banyak 0, gunakan harga terakhir
                        $newHarga = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal1->harga;

                        $jurnal1->update([
                            'total'  => $newTotal,
                            'banyak' => $newBanyak,
                            'm3'     => $newM3,
                            'harga'  => $newHarga,
                            'status' => 'belum sinkron', // Siap untuk Jurnal 2
                        ]);
                    } else {
                        // Inisialisasi Akun Baru di Jurnal 1
                        $initTotal  = $signedNominal;
                        $initBanyak = $addBanyak;
                        $initM3     = $addM3;

                        // Harga Awal: Total / Banyak
                        $initHarga = ($initBanyak != 0)
                            ? ($initTotal / $initBanyak)
                            : (float) ($row->harga ?? 0);

                        Jurnal1st::create([
                            'modif10'   => $modif10Value,
                            'no_akun'   => $noAkunStr,
                            'nama_akun' => $row->nama_akun ?? $row->nama ?? 'AKUN BARU',
                            'bagian'    => 'd', // Default bagian d sesuai template
                            'total'     => $initTotal,
                            'banyak'    => $initBanyak,
                            'm3'        => $initM3,
                            'harga'     => $initHarga,
                            'created_by' => $userName,
                            'status'    => 'belum sinkron',
                        ]);
                    }

                    // 5. Tandai baris Jurnal Umum sebagai 'sudah sinkron'
                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                Log::info("Sinkronisasi berhasil: {$totalProcessed} baris diproses.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Sinkronisasi Jurnal Umum ke Jurnal 1: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Logika Nominal (Total Uang) sesuai Kolom N Excel
     */
    private function resolveNominal(JurnalUmum $row): float
    {
        $hit    = strtolower(trim((string) ($row->hit_kbk ?? '')));
        $harga  = (float) ($row->harga  ?? 0);
        $banyak = (float) ($row->banyak ?? 0);
        $m3     = (float) ($row->m3     ?? 0);

        if ($hit === 'b') return $banyak * $harga;
        if ($hit === 'm') return $m3 * $harga;
        return $harga;
    }

    /**
     * Logika Volume (Banyak & M3) Langsung dari Database
     * Mengikuti prinsip SUMIF(D) - SUMIF(K)
     */
    private function resolveVolumeDirect(JurnalUmum $row, string $mapInput): array
    {
        $banyak = (float) ($row->banyak ?? 0);
        $m3     = (float) ($row->m3     ?? 0);

        // Jika Debit (+) , Jika Kredit (-)
        if ($mapInput === 'D') {
            return [$banyak, $m3];
        } else {
            return [-$banyak, -$m3];
        }
    }
}
