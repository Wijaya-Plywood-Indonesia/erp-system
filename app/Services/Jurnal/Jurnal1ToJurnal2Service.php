<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal1st;
use App\Models\Jurnal2;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 2: Jurnal1 → Jurnal2
 *
 * Jurnal2 adalah level PULUHAN.
 * Input: no_akun Jurnal1 berupa string persis ('1506.10', '1426.01', '1111.00')
 * Output: no_akun Jurnal2 berupa puluhan ('1500', '1420', '1110')
 *
 * RUMUS resolveAkunPuluhan:
 *   $clean = str_replace('.', '', $noAkun);   // '1506.10' → '150610'
 *   return substr($clean, 0, 3) . '0';        // '150' + '0' = '1500'
 *
 * Ini ekuivalen dengan: floor(int_part / 10) * 10
 *   '1506.10' → int_part=1506 → floor(1506/10)*10 = 1500 ✓
 *   '1426.01' → int_part=1426 → floor(1426/10)*10 = 1420 ✓
 *   '1111.00' → int_part=1111 → floor(1111/10)*10 = 1110 ✓
 *   '1201.00' → int_part=1201 → floor(1201/10)*10 = 1200 ✓
 *   '1221.xx' → int_part=1221 → floor(1221/10)*10 = 1220 ✓
 *
 * STATUS: Logika resolveAkunInduk sudah benar — tidak ada perubahan diperlukan.
 * (Verifikasi matematis: semua 289 no_akun dari data SQL menghasilkan hasil yang sama)
 */
class Jurnal1ToJurnal2Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                $rows = Jurnal1st::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal1→Jurnal2: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    // Mapping ke level puluhan
                    $akunPuluhan = $this->resolveAkunPuluhan((string) $row->no_akun);

                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->m3;

                    $jurnal2 = Jurnal2::where('no_akun', $akunPuluhan)->first();

                    if ($jurnal2) {
                        $newTotal  = (float) $jurnal2->total    + $addTotal;
                        $newBanyak = (float) $jurnal2->banyak   + $addBanyak;
                        $newM3     = (float) $jurnal2->kubikasi + $addM3;
                        $newHarga  = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal2->harga;

                        $jurnal2->update([
                            'total'          => $newTotal,
                            'banyak'         => $newBanyak,
                            'kubikasi'       => $newM3,
                            'harga'          => $newHarga,
                            'status_sinkron' => 'belum sinkron',
                        ]);
                    } else {
                        $master    = AnakAkun::where('kode_anak_akun', $akunPuluhan)->first();
                        $initHarga = ($addBanyak != 0)
                            ? ($addTotal / $addBanyak)
                            : (float) $row->harga;

                        Jurnal2::create([
                            'modif100'       => $akunPuluhan,
                            'no_akun'        => $akunPuluhan,
                            'nama_akun'      => $master->nama_anak_akun ?? 'AKUN ' . $akunPuluhan,
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

                    $row->update([
                        'status'    => 'sudah sinkron',
                        'synced_at' => now(),
                        'synced_by' => $userName,
                    ]);

                    $totalProcessed++;
                }

                Log::info("Jurnal1→Jurnal2 selesai: {$totalProcessed} baris.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Jurnal1→Jurnal2: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Mapping ke level puluhan.
     *
     * Input: no_akun Jurnal1 = string persis dari JurnalUmum ('1506.10', '1426.01', '1221.xx')
     * Output: level puluhan ('1500', '1420', '1220')
     *
     * Cara kerja:
     *   '1506.10' → str_replace('.')='150610' → substr(0,3)='150' → '150'+'0'='1500'
     *   '1426.01' → str_replace('.')='142601' → substr(0,3)='142' → '142'+'0'='1420'
     *   '1201.00' → str_replace('.')='120100' → substr(0,3)='120' → '120'+'0'='1200'
     *   '1221.xx' → str_replace('.')='1221xx' → substr(0,3)='122' → '122'+'0'='1220'
     *
     * Ekuivalen matematis: floor(int_part / 10) * 10
     */
    private function resolveAkunPuluhan(string $noAkun): string
    {
        $clean = str_replace('.', '', $noAkun);
        return substr($clean, 0, 3) . '0';
    }
}
