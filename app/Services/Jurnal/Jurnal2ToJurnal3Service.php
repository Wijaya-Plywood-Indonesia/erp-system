<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal2;
use App\Models\JurnalTiga;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Step 3: Jurnal2 → Jurnal3
 *
 * Jurnal3 adalah level RATUSAN.
 * Input: no_akun Jurnal2 berupa string puluhan ('1500', '1420', '2230', '4110')
 * Output: no_akun Jurnal3 berupa ratusan ('1500', '1400', '2200', '4100')
 *
 * RUMUS resolveAkunRatusan:
 *   floor(int($noAkun) / 100) * 100
 *   '1500' → floor(1500/100)*100 = 1500
 *   '1420' → floor(1420/100)*100 = 1400
 *   '2230' → floor(2230/100)*100 = 2200
 *   '4110' → floor(4110/100)*100 = 4100
 *
 * =========================================================================
 * PERBAIKAN UTAMA vs VERSI SEBELUMNYA:
 * =========================================================================
 *
 * VERSI LAMA (BERMASALAH):
 *   $akun = AnakAkun::where('kode_anak_akun', $row->no_akun)->first();
 *   if (!$akun) {
 *       Log::warning("...tidak ditemukan di master.");
 *       continue;  // ← DATA DILEWATI! Ini penyebab utama hasil berbeda
 *   }
 *   $akunSeratus = $akun->parentAkun ?? $akun;    // bergantung relasi Eloquent
 *   $indukAkun   = $akunSeratus->indukAkun;       // bergantung relasi Eloquent
 *   $kodeSeratus = $akunSeratus->kode_anak_akun;
 *
 * MASALAHNYA: no_akun di Jurnal2 adalah '1500', '1420', '2230' (level puluhan).
 * Kode-kode ini mungkin TIDAK ADA di tabel master anak_akuns.
 * Akibatnya: AnakAkun::where() → null → continue → data dilewati → Jurnal3 kosong.
 *
 * VERSI BARU (BENAR):
 *   Hitung level ratusan secara matematis — tidak bergantung pada master DB.
 *   floor(int($noAkun) / 100) * 100
 *   Nama akun: cukup fallback ke nama dari Jurnal2 jika master tidak ada.
 *
 * =========================================================================
 */
class Jurnal2ToJurnal3Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                $rows = Jurnal2::whereRaw('LOWER(status_sinkron) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info('Jurnal2→Jurnal3: Tidak ada data yang perlu disinkron.');
                    return 0;
                }

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    // [FIX] Hitung level ratusan secara matematis
                    // Tidak lagi bergantung pada AnakAkun::where() yang bisa null → skip
                    $akunRatusan = $this->resolveAkunRatusan((string) $row->no_akun);
                    $modif1000   = $this->resolveModif1000($akunRatusan);

                    $addTotal  = (float) $row->total;
                    $addBanyak = (float) $row->banyak;
                    $addM3     = (float) $row->kubikasi;

                    // Jurnal3 key = akun_seratus (level ratusan)
                    $jurnal3 = JurnalTiga::where('akun_seratus', $akunRatusan)->first();

                    if ($jurnal3) {
                        $newTotal  = (float) $jurnal3->total    + $addTotal;
                        $newBanyak = (float) $jurnal3->banyak   + $addBanyak;
                        $newM3     = (float) $jurnal3->kubikasi + $addM3;
                        $newHarga  = ($newBanyak != 0)
                            ? ($newTotal / $newBanyak)
                            : (float) $jurnal3->harga;

                        $jurnal3->update([
                            'total'    => $newTotal,
                            'banyak'   => $newBanyak,
                            'kubikasi' => $newM3,
                            'harga'    => $newHarga,
                            'status'   => 'belum sinkron',
                        ]);
                    } else {
                        // Nama: coba dari master, fallback ke nama Jurnal2 atau default
                        $namaDetail = AnakAkun::where('kode_anak_akun', $akunRatusan)
                            ->value('nama_anak_akun')
                            ?? $row->nama_akun
                            ?? 'AKUN ' . $akunRatusan;

                        $initHarga = ($addBanyak != 0)
                            ? ($addTotal / $addBanyak)
                            : (float) $row->harga;

                        JurnalTiga::create([
                            'modif1000'    => $modif1000,
                            'akun_seratus' => $akunRatusan,
                            'detail'       => $namaDetail,
                            'banyak'       => $addBanyak,
                            'kubikasi'     => $addM3,
                            'total'        => $addTotal,
                            'harga'        => $initHarga,
                            'createdBy'    => $userName,
                            'status'       => 'belum sinkron',
                        ]);
                    }

                    $row->update([
                        'status_sinkron' => 'sudah sinkron',
                        'synced_at'      => now(),
                        'synced_by'      => $userName,
                    ]);

                    $totalProcessed++;
                }

                Log::info("Jurnal2→Jurnal3 selesai: {$totalProcessed} baris.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error('Gagal Jurnal2→Jurnal3: ' . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Mapping ke level ratusan.
     *
     * Input: no_akun Jurnal2 = string puluhan ('1500', '1420', '2230', '4110')
     * Output: string ratusan  ('1500', '1400', '2200', '4100')
     *
     * Contoh lengkap:
     *   '1110' → floor(1110/100)*100 = 1100
     *   '1200' → floor(1200/100)*100 = 1200
     *   '1300' → floor(1300/100)*100 = 1300
     *   '1410' → floor(1410/100)*100 = 1400
     *   '1420' → floor(1420/100)*100 = 1400
     *   '1440' → floor(1440/100)*100 = 1400
     *   '1460' → floor(1460/100)*100 = 1400
     *   '1470' → floor(1470/100)*100 = 1400
     *   '1500' → floor(1500/100)*100 = 1500
     *   '2210' → floor(2210/100)*100 = 2200
     *   '2230' → floor(2230/100)*100 = 2200
     *   '4110' → floor(4110/100)*100 = 4100
     *   '6110' → floor(6110/100)*100 = 6100
     */
    private function resolveAkunRatusan(string $noAkun): string
    {
        $intPart = (int) $noAkun;
        return (string) ((int) floor($intPart / 100) * 100);
    }

    /**
     * Mapping ke level ribuan (untuk kolom modif1000 di JurnalTiga).
     *
     * Input: string ratusan ('1400', '2200', '4100')
     * Output: string ribuan ('1000', '2000', '4000')
     */
    private function resolveModif1000(string $akunRatusan): string
    {
        $intPart = (int) $akunRatusan;
        return (string) ((int) floor($intPart / 1000) * 1000);
    }
}
