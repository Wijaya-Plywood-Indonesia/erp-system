<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Neraca;
use App\Models\IndukAkun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil semua data Jurnal Umum yang belum disinkronkan
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) return 0;

                $totalProcessed = 0;

                foreach ($rows as $row) {
                    // 2. Identifikasi Kelompok Ribuan (Modif 1000)
                    // Contoh: 1111.00 -> 1000, 2110.00 -> 2000
                    $noAkunRaw = (float) $row->no_akun;
                    $kodeInduk = floor($noAkunRaw / 1000) * 1000;
                    $noAkunInduk = number_format($kodeInduk, 0, '.', '');

                    // 3. Logika SUMIF (Debit vs Kredit)
                    // Mengambil nilai nominal baris
                    $nominal = ($row->hit_kbk === 'banyak')
                        ? (float)$row->banyak * (float)$row->harga
                        : (float)$row->m3 * (float)$row->harga;

                    // Rumus: Jika Map = D maka (+), jika Map = K maka (-)
                    $signedNominal = (strtoupper($row->map) === 'D' || $row->map === 'Debit')
                        ? $nominal
                        : -$nominal;

                    // 4. Update atau Create ke Tabel Neraca
                    $neraca = Neraca::where('akun_seribu', $noAkunInduk)->first();

                    if ($neraca) {
                        // Akumulasi: Saldo Neraca Sekarang + (Total Debit - Total Kredit baru)
                        $newTotal = (float)$neraca->total + $signedNominal;

                        $neraca->update([
                            'total' => $newTotal,
                            // Update volume jika diperlukan untuk pelaporan
                            'banyak' => (float)$neraca->banyak + ($row->hit_kbk === 'banyak' ? (float)$row->banyak : 0),
                            'kubikasi' => (float)$neraca->kubikasi + ($row->hit_kbk === 'm3' ? (float)$row->m3 : 0),
                        ]);
                    } else {
                        // Ambil Nama Induk dari Model IndukAkun secara dinamis
                        $namaInduk = IndukAkun::where('kode_induk_akun', $noAkunInduk)->value('nama_induk_akun');

                        Neraca::create([
                            'akun_seribu' => $noAkunInduk,
                            'detail'      => $namaInduk ?? 'Akun Induk ' . $noAkunInduk,
                            'banyak'      => ($row->hit_kbk === 'banyak') ? $row->banyak : 0,
                            'kubikasi'    => ($row->hit_kbk === 'm3') ? $row->m3 : 0,
                            'harga'       => $row->harga,
                            'total'       => $signedNominal,
                        ]);
                    }

                    // 5. Tandai baris asli sebagai 'sudah sinkron'
                    $row->update([
                        'status' => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => auth()->user()->name ?? 'System',
                    ]);

                    $totalProcessed++;
                }

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error("Gagal Sync SUMIF Neraca: " . $e->getMessage());
                throw $e;
            }
        });
    }
}
