<?php

namespace App\Filament\Pages\Absen\Transformers;

use Carbon\Carbon;
use App\Models\Target;
use Illuminate\Support\Facades\Log;

class PressDryerWorkerMap
{
    public static function make($collection): array
    {
        $results = [];

        foreach ($collection as $item) {

            /* ============================================================
             * 1. MESIN, SHIFT, TANGGAL & LABEL
             * ============================================================ */

            $firstMesin = $item->detailMesins->first();
            $namaMesin = '-';
            $mesinUtamaId = null;

            if ($firstMesin) {
                $namaMesin = $firstMesin->mesin->nama_mesin
                    ?? $firstMesin->kategoriMesin->nama_kategori_mesin
                    ?? 'MESIN ?';
                $mesinUtamaId = $firstMesin->id_mesin_dryer;
            }

            $shift = strtoupper($item->shift ?? 'PAGI');

            // --- PERBAIKAN LABEL: HANYA DRYER - SHIFT ---
            if (stripos($namaMesin, 'DRYER') !== false) {
                $labelDivisi = "DRYER - " . $shift;
            } else {
                $labelDivisi = strtoupper($namaMesin);
            }

            /* ============================================================
             * 2. HASIL PRODUKSI
             * ============================================================ */

            $totalHasil = 0;
            $ukuranId = null;

            if ($item->detailHasils->isNotEmpty()) {
                $totalHasil = $item->detailHasils->sum('isi');
                $ukuranId = $item->detailHasils->first()->id_ukuran ?? null;
            }

            /* ============================================================
             * 3. CARI TARGET (LOGIKA DRYER VS NON-DRYER)
             * ============================================================ */

            $targetModel = null;

            if ($mesinUtamaId) {
                // A. LOGIKA KHUSUS DRYER (TARGET BERDASARKAN SHIFT)
                if (stripos($namaMesin, 'DRYER') !== false) {
                    if ($shift === 'PAGI') {
                        $targetModel = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } elseif ($shift === 'MALAM') {
                        $targetModel = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    }
                }
                // B. LOGIKA MESIN LAIN (BERDASARKAN UKURAN / DEFAULT MESIN)
                else {
                    $targetModel = Target::where('id_mesin', $mesinUtamaId)
                        ->when($ukuranId !== null, function ($q) use ($ukuranId) {
                            return $q->where('id_ukuran', $ukuranId);
                        })
                        ->first();

                    if (!$targetModel) {
                        $targetModel = Target::where('id_mesin', $mesinUtamaId)
                            ->whereNull('id_ukuran')
                            ->first();
                    }
                }
            }

            // Fallback Terakhir
            if ($targetModel === null && $mesinUtamaId) {
                if (stripos($namaMesin, 'DRYER') !== false) {
                    $targetModel = Target::where('kode_ukuran', 'DRYER ' . $shift)->first();
                } else {
                    $targetModel = Target::where('id_mesin', $mesinUtamaId)
                        ->whereNull('id_ukuran')
                        ->first();
                }
            }

            // Ambil Data Target
            $targetHarian = (int) ($targetModel->target ?? 0);
            $potonganPerLembar = (int) ($targetModel->potongan ?? 0);

            // Debugging
            Log::info("Processing Dryer ID: {$item->id} | Mesin: {$namaMesin} | Shift: {$shift}", [
                'Target' => $targetHarian,
                'Hasil' => $totalHasil,
                'Selisih' => $totalHasil - $targetHarian
            ]);

            /* ============================================================
             * 4. HITUNG POTONGAN (PEMBULATAN 3 TINGKAT)
             * ============================================================ */

            $selisihProduksi = $totalHasil - $targetHarian;
            $potonganPerOrang = 0;

            if ($targetHarian > 0 && $selisihProduksi < 0 && $potonganPerLembar > 0) {

                $totalKendalaMenit = 0; // Bisa dihubungkan ke relasi kendala jika ada
                $jamKerja = (float) ($targetModel->jam ?? 0);
                $jamKerjaMenit = $jamKerja * 60;

                $targetPerMenit = $jamKerjaMenit > 0 ? ($targetHarian / $jamKerjaMenit) : 0;
                $kekuranganToleransi = $targetPerMenit * $totalKendalaMenit;

                $kekuranganTotal = abs($selisihProduksi);
                $kekuranganPerforma = max(0, $kekuranganTotal - $kekuranganToleransi);

                if ($kekuranganPerforma > 0) {
                    $jumlahPekerja = $item->detailPegawais->count();

                    if ($jumlahPekerja > 0) {
                        $potonganTotal = $kekuranganPerforma * $potonganPerLembar;
                        $potonganRaw = $potonganTotal / $jumlahPekerja;

                        // PEMBULATAN 3 TINGKAT
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
            }

            /* ============================================================
             * 5. MAPPING PEGAWAI (OUTPUT ARRAY DATAR)
             * ============================================================ */

            foreach ($item->detailPegawais as $dp) {
                if (!$dp->pegawai)
                    continue;

                // --- FORMAT WAKTU DENGAN DETIK 00:00:00 ---
                $jamMasuk = $dp->masuk ? Carbon::parse($dp->masuk)->format('H:i:s') : '-';
                $jamPulang = $dp->pulang ? Carbon::parse($dp->pulang)->format('H:i:s') : '-';

                $potonganFinal = $dp->potongan ?? $potonganPerOrang;

                $results[] = [
                    'kodep' => $dp->pegawai->kode_pegawai ?? '-',
                    'nama' => $dp->pegawai->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $jamMasuk,
                    'pulang' => $jamPulang,
                    'hasil' => $labelDivisi, // DRYER - PAGI atau DRYER - MALAM
                    'ijin' => $dp->ijin ?? '',
                    'potongan_targ' => (int) $potonganFinal,
                    'keterangan' => $dp->keterangan ?? '',
                ];
            }
        }

        return $results;
    }
}
