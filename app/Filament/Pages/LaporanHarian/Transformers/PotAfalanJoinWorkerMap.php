<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class PotAfalanJoinWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        if (!$collection || $collection->isEmpty()) return [];

        foreach ($collection as $produksi) {
            $daftarHasil = $produksi->hasilPotAfJoint ?? [];
            $daftarPegawai = $produksi->pegawaiPotAfJoint ?? [];

            foreach ($daftarHasil as $hasil) {
                $ukuranModel = $hasil->ukuran;
                if (!$ukuranModel) continue;

                // --- KONSTRUKSI SESUAI PERMINTAAN ---
                // Format: "POT AFALAN JOINT" + Panjang + Lebar
                $kodeUkuran = "POT AFALAN JOINT" . $ukuranModel->panjang . $ukuranModel->lebar;

                // --- LOG VISUAL TRACE ---
                // Karakter | membantu melihat spasi yang tidak terlihat
                Log::info("--- [DEBUG POT AFALAN START] ---");
                Log::info("📍 String yang dicari ke DB : |{$kodeUkuran}|");

                $targetModel = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin ?? null)
                    ->first() ?? Target::where('kode_ukuran', $kodeUkuran)->first();

                if ($targetModel) {
                    Log::info("✅ STATUS: DITEMUKAN");
                    Log::info("📈 Data Target : Wajib={$targetModel->target}, Potongan/Lbr={$targetModel->potongan}");
                } else {
                    Log::error("❌ STATUS: TIDAK DITEMUKAN di Tabel Targets");
                    Log::error("💡 Tips: Cek apakah di DB tulisannya |POT AFALAN JOINT 66 65| (pakai spasi di angka) atau |POT AFALAN JOINT6665|");
                }

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                foreach ($daftarPegawai as $ppj) {
                    if (!$ppj->pegawai) continue;

                    $hasilGrup = collect($daftarHasil)
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->sum('jumlah');

                    $selisih = $hasilGrup - $targetWajib;
                    $potonganPerOrang = 0;

                    if ($selisih < 0 && $targetWajib > 0 && $potonganPerLembar > 0) {
                        $nominalPotongan = abs($selisih) * $potonganPerLembar;
                        $ribuan = floor($nominalPotongan / 1000);
                        $ratusan = $nominalPotongan % 1000;

                        if ($ratusan < 300) $potonganPerOrang = $ribuan * 1000;
                        elseif ($ratusan < 800) $potonganPerOrang = ($ribuan * 1000) + 500;
                        else $potonganPerOrang = ($ribuan + 1) * 1000;

                        Log::info("💰 HASIL: Potongan Rp " . number_format($potonganPerOrang) . " untuk " . $ppj->pegawai->nama_pegawai);
                    }

                    $results[] = [
                        'kodep' => $ppj->pegawai->kode_pegawai ?? '-',
                        'nama' => $ppj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $ppj->masuk ? Carbon::parse($ppj->masuk)->format('H:i') : '',
                        'pulang' => $ppj->pulang ? Carbon::parse($ppj->pulang)->format('H:i') : '',
                        'hasil' => 'POT AFALAN',
                        'ijin' => $ppj->ijin ?? '',
                        'potongan_targ' => (int) ($ppj->potongan ?? $potonganPerOrang),
                        'keterangan' => $ppj->ket ?? '',
                    ];
                }
                Log::info("--- [DEBUG POT AFALAN END] ---");
            }
        }
        return $results;
    }
}
