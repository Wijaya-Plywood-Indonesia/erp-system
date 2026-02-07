<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class KediWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $produksi) {

            // --- A. TENTUKAN KODE TARGET BERDASARKAN STATUS ---
            // Default kode
            $kodeTargetDicari = 'KEDI';

            // Jika status BONGKAR, cari target 'BONGKAR'
            if ($produksi->status === 'bongkar') {
                $kodeTargetDicari = 'BONGKAR';
            }
            // Jika status MASUK, cari target 'MASUK' (atau bisa diset 'KEDI' jika mau umum)
            elseif ($produksi->status === 'masuk') {
                $kodeTargetDicari = 'MASUK';
            }

            // --- B. CARI TARGET DI DATABASE ---
            $targetRef = Target::where('kode_ukuran', $kodeTargetDicari)->first();

            $stdTarget = (int) ($targetRef->target ?? 0);
            $stdPotHarga = (int) ($targetRef->potongan ?? 0);

            // Log Debugging
            if (!$targetRef) {
                Log::warning("⚠️ [KEDI DEBUG] Target dengan kode '{$kodeTargetDicari}' TIDAK DITEMUKAN untuk ID Produksi {$produksi->id}");
            }

            // --- C. AMBIL DATA HASIL ---
            $totalHasil = 0;
            $detailItems = collect();
            $labelDivisi = "KEDI";

            if ($produksi->status === 'bongkar') {
                if ($produksi->detailBongkarKedi) {
                    $totalHasil = $produksi->detailBongkarKedi->sum('jumlah');
                    $detailItems = $produksi->detailBongkarKedi;
                }
                $labelDivisi = "KEDI (BONGKAR)";
            } elseif ($produksi->status === 'masuk') {
                if ($produksi->detailMasukKedi) {
                    $totalHasil = $produksi->detailMasukKedi->sum('jumlah');
                    $detailItems = $produksi->detailMasukKedi;
                }
                $labelDivisi = "KEDI (MASUK)";
            }

            // Tambah info kayu ke label
            $firstItem = $detailItems->first();
            if ($firstItem) {
                $infoKayu = $firstItem->jenisKayu->nama_kayu ?? '';
                if ($infoKayu)
                    $labelDivisi .= " - " . $infoKayu;
            }

            // --- D. HITUNG SELISIH & POTONGAN ---
            $selisih = $totalHasil - $stdTarget;
            $potonganPerOrang = 0;

            if ($stdTarget > 0 && $selisih < 0 && $stdPotHarga > 0) {

                $jumlahPekerja = $produksi->detailPegawaiKedi ? $produksi->detailPegawaiKedi->count() : 0;

                if ($jumlahPekerja > 0) {
                    $kekurangan = abs($selisih);
                    $totalPot = $kekurangan * $stdPotHarga;
                    $potonganRaw = $totalPot / $jumlahPekerja;

                    // Pembulatan 3 Tingkat
                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrang = $ribuan * 1000;
                    } elseif ($ratusan >= 300 && $ratusan < 800) {
                        $potonganPerOrang = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrang = ($ribuan + 1) * 1000;
                    }
                }
            }

            // --- E. MAPPING PEGAWAI ---
            if ($produksi->detailPegawaiKedi) {
                foreach ($produksi->detailPegawaiKedi as $dp) {
                    if (!$dp->pegawai)
                        continue;

                    $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '';
                    $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '';
                    $potonganFinal = $dp->potongan ?? $potonganPerOrang;

                    $results[] = [
                        'kodep' => $dp->pegawai->kode_pegawai ?? '-',
                        'nama' => $dp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                        'masuk' => $jamMasuk,
                        'pulang' => $jamPulang,
                        'hasil' => $labelDivisi,
                        'ijin' => $dp->ijin ?? '',
                        'potongan_targ' => (int) $potonganFinal,
                        'keterangan' => $dp->keterangan ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
