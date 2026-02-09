<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {
            try {
                $rows = JurnalUmum::whereRaw('LOWER(status) = ?', ['belum sinkron'])->get();

                if ($rows->isEmpty()) {
                    Log::info("Sinkronisasi: Tidak ada data Jurnal Umum yang perlu diproses.");
                    return 0;
                }

                $noAkunList = $rows->pluck('no_akun')->unique()->toArray();
                $existingJurnals = Jurnal1st::whereIn('no_akun', $noAkunList)
                    ->whereRaw('LOWER(status) = ?', ['belum sinkron'])
                    ->get()
                    ->keyBy('no_akun');

                $totalProcessed = 0;
                $userName = Auth::user()?->name ?? 'System';

                foreach ($rows as $row) {
                    $noAkunRaw = (string) $row->no_akun;
                    $parts = explode('.', str_replace(',', '.', $noAkunRaw));
                    $angkaDepanKoma = (int) $parts[0];
                    $modif10Value = floor($angkaDepanKoma / 10) * 10;

                    $mapInput = strtoupper($row->map);
                    $rowHarga = (float) ($row->harga ?? 0);
                    $rowBanyak = (float) ($row->banyak ?? 0);
                    $rowM3 = (float) ($row->m3 ?? 0);

                    $isBanyak = ($row->hit_kbk === 'banyak');
                    $nominalBaris = $isBanyak ? ($rowBanyak * $rowHarga) : ($rowM3 * $rowHarga);

                    $signedNominal = ($mapInput === 'D') ? $nominalBaris : -$nominalBaris;
                    $volMutasi = $isBanyak ? $rowBanyak : $rowM3;
                    $signedVolume = $volMutasi * (($mapInput === 'D') ? 1 : -1);

                    $existing = $existingJurnals->get($noAkunRaw);

                    if ($existing) {
                        $bagianDB = strtoupper($existing->bagian);
                        $totalLamaSigned = ($bagianDB === 'D') ? (float)$existing->total : -(float)$existing->total;
                        $volLama = $isBanyak ? (float)$existing->banyak : (float)$existing->m3;
                        $volLamaSigned = ($bagianDB === 'D') ? $volLama : -$volLama;

                        $totalBaruSigned = $totalLamaSigned + $signedNominal;
                        $volBaruSigned = $volLamaSigned + $signedVolume;

                        $absTotalBaru = abs($totalBaruSigned);
                        $absVolBaru = abs($volBaruSigned);
                        $bagianBaru = ($totalBaruSigned >= 0) ? 'D' : 'K';
                        $finalHarga = ($absVolBaru > 0) ? ($absTotalBaru / $absVolBaru) : 0;

                        $existing->update([
                            'banyak' => $isBanyak ? $absVolBaru : $existing->banyak,
                            'm3'     => !$isBanyak ? $absVolBaru : $existing->m3,
                            'harga'  => $finalHarga,
                            'total'  => $absTotalBaru,
                            'bagian' => $bagianBaru,
                        ]);
                    } else {
                        Jurnal1st::create([
                            'modif10'    => (string) $modif10Value,
                            'no_akun'    => $noAkunRaw,
                            'nama_akun'  => $row->nama_akun ?? $row->nama,
                            'bagian'     => $mapInput,
                            'banyak'     => $isBanyak ? $rowBanyak : 0,
                            'm3'         => !$isBanyak ? $rowM3 : 0,
                            'harga'      => $rowHarga,
                            'total'      => $nominalBaris,
                            'created_by' => $row->created_by,
                            'status'     => 'belum sinkron',
                        ]);
                    }

                    $this->handleCleanup($row, $userName);
                    $totalProcessed++;
                }

                Log::info("Sinkronisasi Sukses: Berhasil memindahkan $totalProcessed baris.");
                return $totalProcessed;
            } catch (\Exception $e) {
                Log::error("Gagal Sinkronisasi Jurnal: " . $e->getMessage());
                throw $e;
            }
        });
    }

    protected function handleCleanup($row, $userName)
    {
        $date = Carbon::parse($row->tgl);
        if ($date->isWednesday() || $date->isThursday()) {
            $row->update([
                'status'    => 'sudah sinkron',
                'synced_at' => now(),
                'synced_by' => $userName,
            ]);
        } else {
            $row->delete();
        }
    }
}
