<?php

namespace App\Filament\Pages\LaporanHarian\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class JointWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {
            foreach ($produksi->hasilJoint as $hasil) {
                $ukuranModel = $hasil->ukuran;
                $jenisKayuModel = $hasil->jenisKayu;

                $kwRaw = trim($hasil->kw ?? '');
                $kodeKayu = strtoupper(trim($jenisKayuModel->kode_kayu ?? ''));

                $labelPekerjaan = 'JOINT';

                $kodeUkuran = 'JOINT-NOT-FOUND';
                if ($ukuranModel && $jenisKayuModel) {

                    // --- LOGIKA FINAL ---
                    // Jika Kayu adalah 'S', gunakan 'S'. Jika bukan 'S', kosongkan (hapus dari daftar).
                    $suffixKayu = ($kodeKayu === 'S') ? 'S' : '';

                    // Konstruksi: JOINT + P + L + T(koma) + KW + S (jika ada)
                    $kodeUkuran = 'JOINT' .
                        $ukuranModel->panjang .
                        $ukuranModel->lebar .
                        str_replace('.', ',', $ukuranModel->tebal) .
                        $kwRaw .
                        $suffixKayu;

                    // Pastikan tidak ada spasi agar cocok dengan database
                    $kodeUkuran = str_replace(' ', '', $kodeUkuran);
                }

                // --- LOG DEBUG ---
                Log::info("🧩 [JOINT WORKER] Mencari Target: '{$kodeUkuran}' (Kayu: '{$kodeKayu}', KW: '{$kwRaw}')");

                $targetModel = Target::where('kode_ukuran', $kodeUkuran)
                    ->where('id_mesin', $produksi->id_mesin ?? null)
                    ->first() ?? Target::where('kode_ukuran', $kodeUkuran)->first();

                if ($targetModel) {
                    Log::info("✅ [JOINT WORKER] Target Ditemukan: {$targetModel->target}");
                } else {
                    Log::error("❌ [JOINT WORKER] Target TIDAK ADA di DB untuk kode: '{$kodeUkuran}'");
                }

                $targetWajib = (int) ($targetModel->target ?? 0);
                $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

                foreach ($produksi->pegawaiJoint as $pj) {
                    if (!$pj->pegawai) continue;

                    // Hitung Hasil Grup
                    $hasilGrup = $produksi->hasilJoint
                        ->where('id_ukuran', $hasil->id_ukuran)
                        ->where('kw', $kwRaw)
                        ->sum('jumlah');

                    $selisih = $hasilGrup - $targetWajib;
                    $potonganPerOrang = 0;

                    if ($selisih < 0 && $targetWajib > 0 && $potonganPerLembar > 0) {
                        $nominalPotongan = abs($selisih) * $potonganPerLembar;

                        // Pembulatan standar 500
                        $ribuan = floor($nominalPotongan / 1000);
                        $ratusan = $nominalPotongan % 1000;

                        if ($ratusan < 300) $potonganPerOrang = $ribuan * 1000;
                        elseif ($ratusan < 800) $potonganPerOrang = ($ribuan * 1000) + 500;
                        else $potonganPerOrang = ($ribuan + 1) * 1000;
                    }

                    $results[] = [
                        'kodep' => $pj->pegawai->kode_pegawai ?? '-',
                        'nama' => $pj->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $pj->masuk ? Carbon::parse($pj->masuk)->format('H:i') : '',
                        'pulang' => $pj->pulang ? Carbon::parse($pj->pulang)->format('H:i') : '',
                        'hasil' => $labelPekerjaan,
                        'ijin' => $pj->ijin ?? '',
                        'potongan_targ' => (int) ($pj->potongan ?? $potonganPerOrang),
                        'keterangan' => $pj->ket ?? '',
                    ];
                }
            }
        }
        return $results;
    }
}
