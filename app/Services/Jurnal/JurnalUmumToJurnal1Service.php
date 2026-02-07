<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            // Ambil data yang Belum Sinkron
            $rows = JurnalUmum::where('status', 'Belum Sinkron')->get();
            $totalProcessed = 0;

            foreach ($rows as $row) {
                $noAkunRaw = (string) $row->no_akun;

                // 1. LOGIKA MODIF 10
                $angkaDepanKoma = (int) explode('.', str_replace(',', '.', $noAkunRaw))[0];
                $modif10Value = floor($angkaDepanKoma / 10) * 10;

                // 2. NORMALISASI INPUT (Mencegah Case Sensitivity)
                $mapInput = strtoupper($row->map); // Memastikan 'd' atau 'D' menjadi 'D'
                $rowHarga = (float) $row->harga;
                $rowBanyak = (float) ($row->banyak ?? 0);
                $rowM3 = (float) ($row->m3 ?? 0);

                // Tentukan Nominal Baris
                $nominalBaris = ($row->hit_kbk === 'b') ? ($rowBanyak * $rowHarga) : ($rowM3 * $rowHarga);

                // Tentukan Nilai Signed (Debit +, Kredit -)
                $signedNominal = ($mapInput === 'D') ? $nominalBaris : -$nominalBaris;
                $signedVolume = ($row->hit_kbk === 'b' ? $rowBanyak : $rowM3) * (($mapInput === 'D') ? 1 : -1);

                $existing = Jurnal1st::where('no_akun', $noAkunRaw)
                    ->where('status', 'belum sinkron')
                    ->first();

                if ($existing) {
                    // 3. AMBIL DATA DB DENGAN NORMALISASI BAGIAN
                    $bagianDB = strtoupper($existing->bagian);
                    $totalLamaSigned = ($bagianDB === 'D') ? (float)$existing->total : -(float)$existing->total;

                    $currentVol = ($row->hit_kbk === 'b') ? (float)$existing->banyak : (float)$existing->m3;
                    $volLamaSigned = ($bagianDB === 'D') ? $currentVol : -$currentVol;

                    // 4. LOGIKA NETTING (Saldo Lama + Mutasi Baru)
                    $totalBaruSigned = $totalLamaSigned + $signedNominal;
                    $volBaruSigned = $volLamaSigned + $signedVolume;

                    $absTotalBaru = abs($totalBaruSigned);
                    $absVolBaru = abs($volBaruSigned);
                    $bagianBaru = ($totalBaruSigned >= 0) ? 'D' : 'K';

                    // Hitung Harga Rata-Rata Tertimbang
                    $finalHarga = ($absVolBaru > 0) ? ($absTotalBaru / $absVolBaru) : 0;

                    $existing->update([
                        'banyak' => ($row->hit_kbk === 'b') ? $absVolBaru : $existing->banyak,
                        'm3'     => ($row->hit_kbk === 'm') ? $absVolBaru : $existing->m3,
                        'harga'  => $finalHarga,
                        'total'  => $absTotalBaru,
                        'bagian' => $bagianBaru,
                    ]);
                } else {
                    // Jika Record Pertama
                    Jurnal1st::create([
                        'modif10'    => (string) $modif10Value,
                        'no_akun'    => $noAkunRaw,
                        'nama_akun'  => $row->nama,
                        'bagian'     => $mapInput,
                        'banyak'     => $rowBanyak,
                        'm3'         => $rowM3,
                        'harga'      => $rowHarga,
                        'total'      => $nominalBaris,
                        'created_by' => $row->created_by,
                        'status'     => 'belum sinkron',
                    ]);
                }

                $this->handleCleanup($row);
                $totalProcessed++;
            }

            return $totalProcessed;
        });
    }

    protected function handleCleanup($row)
    {
        $date = Carbon::parse($row->tgl);
        // Retensi Data Rabu & Kamis
        if ($date->isWednesday() || $date->isThursday()) {
            $row->update([
                'status'    => 'Sudah Sinkron',
                'synced_at' => now(),
                'synced_by' => Auth::user()->name,
            ]);
        } else {
            $row->delete();
        }
    }
}
