<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class SandingJoinWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            foreach ($produksi->hasilSandingJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;

                $kwRaw = trim($hasil->kw ?? '');
                $kwLower = strtolower($kwRaw);

                $labelPekerjaan = 'SANDING JOINT';

                $kodeUkuran = 'SANDING-JOINT-NOT-FOUND';
                if ($ukuranModel && $jenisKayuModel) {

                    // Logic: Suffix hanya untuk afs/afm. Angka & Kayu dibuang.
                    $suffix = in_array($kwLower, ['afs', 'afm']) ? $kwRaw : '';

                    // FORMAT: SANDING (spasi) JOINT + P + L + T(koma) + Suffix
                    // Contoh hasil: "SANDING JOINT130661,8afm"
                    $kodeUkuran = 'SANDING JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $suffix;

                    // Kita pastikan bagian setelah "SANDING " tidak ada spasi yang terselip
                    // Namun tetap menjaga spasi pertama antara SANDING dan JOINT
                }

                // --- LOG DEBUG UNTUK VALIDASI FORMAT ---
                Log::info("🔍 [DEBUG SANDING] String Kode: '{$kodeUkuran}'");

                $targetModel = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin ?? null)
                    ->first() ?? Target::where('kode_ukuran', $kodeUkuran)->first();

                if ($targetModel) {
                    Log::info("✅ [DEBUG SANDING] Target Ditemukan: {$targetModel->target}");
                } else {
                    Log::error("❌ [DEBUG SANDING] Target TIDAK ADA di DB: '{$kodeUkuran}'");
                }

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                foreach ($produksi->pegawaiSandingJoint as $psj) {
                    if (!$psj->pegawai) continue;

                    $hasilGrup = $produksi->hasilSandingJoint
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
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
                    }

                    $results[] = [
                        'kodep' => $psj->pegawai->kode_pegawai ?? '-',
                        'nama' => $psj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $psj->masuk ? Carbon::parse($psj->masuk)->format('H:i') : '',
                        'pulang' => $psj->pulang ? Carbon::parse($psj->pulang)->format('H:i') : '',
                        'hasil' => $labelPekerjaan,
                        'ijin' => $psj->ijin ?? '',
                        'potongan_targ' => (int) ($psj->potongan ?? $potonganPerOrang),
                        'keterangan' => $psj->ket ?? '',
                    ];
                }
            }
        }
        return $results;
    }
}
