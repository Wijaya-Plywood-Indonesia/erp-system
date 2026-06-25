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

            $shift = strtoupper(trim($item->shift ?? ''));
            $shift = $shift === '' ? null : $shift;

            // --- HITUNG KENDALA DOWNTIME ---
            $totalKendalaMenit = 0;
            if (!empty($item->kendalaPressDryers) && $item->kendalaPressDryers->count() > 0) {
                foreach ($item->kendalaPressDryers as $knd) {
                    if ($knd->status === 'selesai' && !is_null($knd->durasi_menit)) {
                        $totalKendalaMenit += (int)$knd->durasi_menit;
                    }
                }
            }

            $labelDivisi = $shift ? "DRYER - " . strtoupper($shift) : "DRYER";

            /* ============================================================
             * 2. HASIL PRODUKSI
             * ============================================================ */

            $totalHasil = 0;
            $ukuranId = null;

            if ($item->detailHasils->isNotEmpty()) {
                $ukuranId = $item->detailHasils->first()->id_ukuran ?? null;

                if (stripos($namaMesin, 'DRYER') !== false) {
                    // Dryer uses kubikasi (m3)
                    $totalHasil = $item->detailHasils->sum(function ($dh) {
                        $ukuran = $dh->ukuran ?? null;
                        $panjang = $ukuran?->panjang ?? null;
                        $lebar = $ukuran?->lebar ?? null;
                        $tebal = $ukuran?->tebal ?? null;
                        $isi = $dh->isi ?? 0;

                        if ($panjang && $lebar && $tebal && $isi) {
                            return ($panjang * $lebar * $tebal * $isi) / 10000000;
                        }
                        return 0;
                    });
                    $totalHasil = round($totalHasil, 4);
                } else {
                    $totalHasil = $item->detailHasils->sum('isi');
                }
            }

            /* ============================================================
             * 3. CARI TARGET (LOGIKA DRYER VS NON-DRYER)
             * ============================================================ */

            $target = null;

            if ($mesinUtamaId) {
                // A. LOGIKA KHUSUS DRYER (TARGET BERDASARKAN MESIN / ACUAN SHIFT)
                if (stripos($namaMesin, 'DRYER') !== false) {
                    if ($shift === 'PAGI') {
                        $target = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                    } elseif ($shift === 'MALAM') {
                        $target = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                    }
                } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                    $target = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                    $target = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                }
                // B. LOGIKA MESIN LAIN (BERDASARKAN UKURAN / DEFAULT MESIN)
                else {
                    $target = Target::where('id_mesin', $mesinUtamaId)
                        ->when($ukuranId !== null, function ($q) use ($ukuranId) {
                            return $q->where('id_ukuran', $ukuranId);
                        })
                        ->first();

                    if (!$target) {
                        $target = Target::where('id_mesin', $mesinUtamaId)
                            ->whereNull('id_ukuran')
                            ->first();
                    }
                }
            }

            // Fallback Terakhir
            if ($target === null && $mesinUtamaId) {
                if (stripos($namaMesin, 'DRYER') !== false) {
                    $target = Target::where('kode_ukuran', 'DRYER ' . $shift)->first();
                } elseif (stripos($namaMesin, 'DRYER 1') !== false || $mesinUtamaId == 17) {
                    $target = Target::where('kode_ukuran', 'DRYER PAGI')->first();
                } elseif (stripos($namaMesin, 'DRYER 2') !== false || $mesinUtamaId == 18) {
                    $target = Target::where('kode_ukuran', 'DRYER MALAM')->first();
                } else {
                    $target = Target::where('id_mesin', $mesinUtamaId)
                        ->whereNull('id_ukuran')
                        ->first();
                }
            }

            // Ambil Data Target
            $targetHarian = (float) ($target->target ?? 0);
            $potonganPerLembar = (int) ($target->potongan ?? 0);

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

                $jamKerja = (float) ($target->jam ?? 0);
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
                    'keterangan' => $dp->ket ?? '',
                ];
            }
        }

        return $results;
    }
}
