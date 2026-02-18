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

                $noAkunRaw = number_format((float) $noAkun, 2, '.', '');
                $parts = explode('.', $noAkunRaw);
                $angkaDepanKoma = (int) $parts[0];
                $modif10Value = floor($angkaDepanKoma / 10) * 10;

                $totalNominal = 0;
                $totalVolume  = 0;

                foreach ($items as $row) {

                    $map   = strtolower(trim((string)$row->map));
                    $harga = (float) ($row->harga ?? 0);
                    $banyak = (float) ($row->banyak ?? 0);
                    $m3     = (float) ($row->m3 ?? 0);

                    $hitKbk = strtolower(trim((string)$row->hit_kbk));

                    // Hitung nominal & volume
                    if (empty($hitKbk)) {
                        $nominal = $harga;
                        $volume  = 0;
                    } elseif (in_array($hitKbk, ['b', 'banyak'])) {
                        $nominal = $banyak * $harga;
                        $volume  = $banyak;
                    } else {
                        $nominal = $m3 * $harga;
                        $volume  = $m3;
                    }

                    // Debit tambah, Kredit kurang
                    if ($map === 'd') {
                        $totalNominal += $nominal;
                        $totalVolume  += $volume;
                    } else {
                        $totalNominal -= $nominal;
                        $totalVolume  -= $volume;
                    }
                }

                // Ambil saldo lama jika ada
                $existing = Jurnal1st::where('no_akun', $noAkunRaw)->first();

                if ($existing) {
                    $totalNominal += (float)$existing->total;

                    $volumeExisting = (float)$existing->banyak > 0
                        ? (float)$existing->banyak
                        : (float)$existing->m3;

                    $totalVolume += $volumeExisting;

                    // Hapus saldo lama supaya tidak dobel
                    $existing->delete();
                }

                $finalHarga = ($totalVolume != 0)
                    ? $totalNominal / $totalVolume
                    : 0;

                Jurnal1st::create([
                    'modif10'    => (string) $modif10Value,
                    'no_akun'    => $noAkunRaw,
                    'nama_akun'  => $items->first()->nama_akun
                        ?? $items->first()->nama
                        ?? 'AKUN TIDAK DIKETAHUI',
                    'bagian'     => 'd', // SELALU D
                    'banyak'     => $totalVolume > 0 ? $totalVolume : 0,
                    'm3'         => 0,
                    'harga'      => $finalHarga,
                    'total'      => $totalNominal, // boleh minus
                    'created_by' => $userName,
                    'status'     => 'belum sinkron',
                ]);

                // Update semua row jadi sudah sinkron
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
