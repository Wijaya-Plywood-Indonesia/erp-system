<?php

namespace App\Services\Jurnal;

use App\Models\IndukAkun;
use App\Models\JurnalTiga;
use App\Models\Neraca;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 4: Jurnal3 → Neraca
 *
 * Neraca adalah level RIBUAN (1000, 2000, 3000, 4000, 5000, 6000).
 * Input: modif1000 dari JurnalTiga (sudah dihitung oleh Jurnal2ToJurnal3Service)
 * Output: baris neraca per akun ribuan.
 *
 * STATUS: Tidak ada perubahan — logika ini sudah benar.
 * (Menggunakan $row->modif1000 yang disimpan langsung, tidak bergantung relasi Eloquent)
 *
 * CATATAN PENTING:
 * Pastikan kolom 'total' di tabel neracas bertipe DECIMAL(20,2), BUKAN INT.
 * Nilai akun 3000 (Modal) mencapai -10,260,178,448 yang melebihi batas INT (±2,147,483,647).
 * Overflow INT akan menghasilkan nilai aneh seperti -2,147,483,648.
 */
class Jurnal3ToNeracaService
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                $rows = JurnalTiga::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal3→Neraca: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    $kodeInduk = (string) $row->modif1000;

                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->kubikasi;

                    $neraca = Neraca::where('akun_seribu', $kodeInduk)->first();

                    if ($neraca) {
                        $newTotal  = (float) $neraca->total    + $addTotal;
                        $newBanyak = (float) $neraca->banyak   + $addBanyak;
                        $newM3     = (float) $neraca->kubikasi + $addM3;
                        $newHarga  = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $neraca->harga;

                        $neraca->update([
                            'total'    => $newTotal,
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'harga'    => $newHarga,
                            'detail'   => IndukAkun::where('kode_induk_akun', $kodeInduk)
                                ->value('nama_induk_akun') ?? $neraca->detail,
                        ]);
                    } else {
                        $namaInduk = IndukAkun::where('kode_induk_akun', $kodeInduk)
                            ->value('nama_induk_akun');

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

                    $row->update([
                        'status'          => 'sinkron',
                        'synchronized_by' => $userName,
                        'synchronized_at' => now(),
                    ]);

                    $totalProcessed++;
                }

                Log::info("Jurnal3→Neraca selesai: {$totalProcessed} baris.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Jurnal3→Neraca: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}
