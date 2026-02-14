<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Neraca;
use App\Models\IndukAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalUmumToJurnal1Service
{
    /**
     * Melakukan sinkronisasi akumulasi dari Jurnal Umum langsung ke Neraca.
     * Menggunakan logika SUMIF (Debit - Kredit) per kelompok ribuan.
     */
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                // 1. Ambil data yang belum sinkron
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    // 2. Identifikasi Kelompok Ribuan (Modif 1000)
                    $noAkunRaw = (float) $row->no_akun;
                    $kodeInduk = floor($noAkunRaw / 1000) * 1000;
                    $noAkunInduk = (string) $kodeInduk;

                    // 3. Kalkulasi Nominal (Logika Pengaman)
                    // Jika total di DB ada, gunakan itu. Jika 0, hitung manual.
                    $nominalBaris = (float) ($row->total ?? 0);

                    if ($nominalBaris <= 0) {
                        $nominalBaris = ($row->hit_kbk === 'm3')
                            ? (float)$row->m3 * (float)$row->harga
                            : (float)$row->banyak * (float)$row->harga;
                    }

                    // 4. Logika SUMIF: Debit (+) dan Kredit (-)
                    $mapInput = strtoupper($row->map);
                    $signedNominal = ($mapInput === 'D' || $mapInput === 'DEBIT')
                        ? $nominalBaris
                        : -$nominalBaris;

                    // 5. Update atau Create di Tabel Neraca
                    $neraca = Neraca::where('akun_seribu', $noAkunInduk)->first();

                    if ($neraca) {
                        // Akumulasi Saldo Bersih
                        $newTotal = (float)$neraca->total + $signedNominal;

                        // Akumulasi Volume Fisik (Selalu dijumlahkan agar tidak Nol)
                        $newBanyak = (float)$neraca->banyak + ($row->hit_kbk === 'banyak' ? (float)$row->banyak : 0);
                        $newM3 = (float)$neraca->kubikasi + ($row->hit_kbk === 'm3' ? (float)$row->m3 : 0);

                        $neraca->update([
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'total'    => $newTotal,
                            // Hitung Moving Average Harga jika volume > 0
                            'harga'    => ($newBanyak + $newM3) > 0
                                ? abs($newTotal / ($newBanyak + $newM3))
                                : $neraca->harga,
                        ]);
                    } else {
                        // Ambil Keterangan Nama Induk
                        $namaInduk = IndukAkun::where('kode_induk_akun', $noAkunInduk)->value('nama_induk_akun');

                        Neraca::create([
                            'akun_seribu' => $noAkunInduk,
                            'detail'      => $namaInduk ?? 'Akun Induk ' . $noAkunInduk,
                            'banyak'      => ($row->hit_kbk === 'banyak') ? (float)$row->banyak : 0,
                            'kubikasi'    => ($row->hit_kbk === 'm3') ? (float)$row->m3 : 0,
                            'harga'       => (float)$row->harga,
                            'total'       => $signedNominal,
                        ]);
                    }

                    // 6. Tandai baris di Jurnal Umum agar tidak diproses ulang
                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error("Gagal Sinkronisasi Langsung ke Neraca: " . $e->getMessage());
                throw $e;
            }
        });
    }
}
