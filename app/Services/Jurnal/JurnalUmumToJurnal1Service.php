<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 1: JurnalUmum → Jurnal1
 *
 * Jurnal1 menyimpan no_akun PERSIS seperti di JurnalUmum (termasuk sub-kode: '1506.10', '1426.01')
 * Semua baris dengan no_akun yang sama diagregasi menjadi satu baris net signed.
 *
 * RUMUS NOMINAL (kolom N Excel):
 *   hit_kbk = 'b'  → nominal = banyak × harga
 *   hit_kbk = 'm'  → nominal = m3 × harga
 *   hit_kbk = NULL/lain → nominal = harga
 *
 * RUMUS VOLUME (kolom O/P Excel):
 *   SEMUA banyak/m3 masuk — tidak difilter hit_kbk
 *   Debit (+), Kredit (-)
 *
 * STATUS: Tidak ada perubahan — logika ini sudah benar.
 */
class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('JurnalUmum→Jurnal1: Tidak ada data baru.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    $mapInput = strtoupper(trim((string) $row->map));

                    if (!in_array($mapInput, ['D', 'K'])) {
                        Log::warning("Baris id={$row->id} dilewati: map '{$row->map}' tidak valid.");
                        continue;
                    }

                    // no_akun PERSIS dari DB, termasuk sub-kode ('1506.10', '1426.01', '1111.00')
                    $noAkunStr = trim((string) $row->no_akun);
                    $noAkunInt = (int) explode('.', $noAkunStr)[0];

                    // modif10 = FLOOR(int_part / 10) * 10
                    $modif10 = (string) ((int) floor($noAkunInt / 10) * 10);

                    $nominal       = $this->resolveNominal($row);
                    $signedNominal = ($mapInput === 'D') ? $nominal : -$nominal;

                    [$addBanyak, $addM3] = $this->resolveVolume($row, $mapInput);

                    $jurnal1 = Jurnal1st::where('no_akun', $noAkunStr)->first();

                    if ($jurnal1) {
                        $newTotal  = (float) $jurnal1->total  + $signedNominal;
                        $newBanyak = (float) $jurnal1->banyak + $addBanyak;
                        $newM3     = (float) $jurnal1->m3     + $addM3;
                        $newHarga  = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal1->harga;

                        $jurnal1->update([
                            'total'  => $newTotal,
                            'banyak' => $newBanyak,
                            'm3'     => $newM3,
                            'harga'  => $newHarga,
                            'status' => 'belum sinkron',
                        ]);
                    } else {
                        $initHarga = ($addBanyak != 0)
                            ? ($signedNominal / $addBanyak)
                            : (float) ($row->harga ?? 0);

                        Jurnal1st::create([
                            'modif10'    => $modif10,
                            'no_akun'    => $noAkunStr,
                            'nama_akun'  => $row->nama_akun ?? $row->nama ?? 'AKUN ' . $noAkunStr,
                            'bagian'     => strtolower($mapInput),
                            'total'      => $signedNominal,
                            'banyak'     => $addBanyak,
                            'm3'         => $addM3,
                            'harga'      => $initHarga,
                            'created_by' => $userName,
                            'status'     => 'belum sinkron',
                        ]);
                    }

                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                Log::info("JurnalUmum→Jurnal1 selesai: {$totalProcessed} baris.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal JurnalUmum→Jurnal1: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    private function resolveNominal(JurnalUmum $row): float
    {
        $hit   = strtolower(trim((string) ($row->hit_kbk ?? '')));
        $harga = (float) ($row->harga  ?? 0);
        $byk   = (float) ($row->banyak ?? 0);
        $m3    = (float) ($row->m3     ?? 0);

        if ($hit === 'b') return $byk * $harga;
        if ($hit === 'm') return $m3  * $harga;
        return $harga;
    }

    private function resolveVolume(JurnalUmum $row, string $mapInput): array
    {
        $banyak = (float) ($row->banyak ?? 0);
        $m3     = (float) ($row->m3     ?? 0);

        return ($mapInput === 'D') ? [$banyak, $m3] : [-$banyak, -$m3];
    }
}
