<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            // 1. Ambil data yang belum sinkron
            $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])
                ->get()
                ->groupBy('no_akun');

            if ($rows->isEmpty()) {
                Log::info("Sinkronisasi: Tidak ada data yang perlu diproses.");
                return 0;
            }

            $totalProcessed = 0;
            $userName = Auth::user()?->name ?? 'System';

            foreach ($rows as $noAkun => $items) {

                $noAkunRaw = $noAkun;
                $parts = explode('.', $noAkunRaw);
                $angkaDepanKoma = (int) $parts[0];
                $modif10Value = floor($angkaDepanKoma / 10) * 10;

                // Gunakan string '0' agar presisi BCMath terjaga
                $batchNominal = '0.0000000000'; 
                $batchVolume  = '0.0000';

                foreach ($items as $row) {
                    $map    = strtolower(trim((string)$row->map));
                    $harga  = (string)($row->harga ?? '0');
                    $banyak = (string)($row->banyak ?? '0');
                    $m3     = (string)($row->m3 ?? '0');
                    $hitKbk = strtolower(trim((string)$row->hit_kbk));

                    // 2. Hitung nominal baris ini dengan presisi tinggi
                    if (empty($hitKbk)) {
                        $nominal = $harga;
                        $volume  = '0';
                    } elseif (in_array($hitKbk, ['b', 'banyak'])) {
                        $nominal = bcmul($banyak, $harga, 10);
                        $volume  = $banyak;
                    } else {
                        $nominal = bcmul($m3, $harga, 10);
                        $volume  = $m3;
                    }

                    // 3. Akumulasi berdasarkan Debit (tambah) atau Kredit (kurang)
                    if ($map === 'd') {
                        $batchNominal = bcadd($batchNominal, $nominal, 10);
                        $batchVolume  = bcadd($batchVolume, $volume, 4);
                    } else {
                        $batchNominal = bcsub($batchNominal, $nominal, 10);
                        $batchVolume  = bcsub($batchVolume, $volume, 4);
                    }
                }

                // 4. Ambil saldo lama yang sudah ada di Jurnal 1st (jika ada)
                $existing = Jurnal1st::where('no_akun', $noAkunRaw)->first();
                
                $finalNominal = $batchNominal;
                $finalVolume  = $batchVolume;

                if ($existing) {
                    // Tambahkan hasil batch baru ke saldo yang sudah ada
                    $finalNominal = bcadd($batchNominal, (string)$existing->total, 10);
                    
                    // Ambil volume lama (cek banyak atau m3)
                    $oldVol = bccomp((string)$existing->banyak, '0', 4) !== 0 
                              ? (string)$existing->banyak 
                              : (string)$existing->m3;
                    
                    $finalVolume = bcadd($batchVolume, (string)$oldVol, 4);
                }

                // 5. Update atau Simpan ke Jurnal 1st
                // Rounding ke 2 desimal hanya dilakukan di sini (hasil akhir)
                Jurnal1st::updateOrCreate(
                    ['no_akun' => $noAkunRaw],
                    [
                        'modif10'    => (string) $modif10Value,
                        'nama_akun'  => $items->first()->nama_akun 
                                        ?? $items->first()->nama 
                                        ?? 'AKUN TIDAK DIKETAHUI',
                        'bagian'     => 'd',
                        'banyak'     => $finalVolume,
                        'm3'         => 0,
                        'harga'      => 0,
                        'total'      => bcadd($finalNominal, '0', 2), 
                        'created_by' => $userName,
                        'status'     => 'belum sinkron',
                    ]
                );

                // 6. Tandai Jurnal Umum sebagai sudah sinkron
                foreach ($items as $row) {
                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);
                    $totalProcessed++;
                }
            }

            return $totalProcessed;
        });
    }
}