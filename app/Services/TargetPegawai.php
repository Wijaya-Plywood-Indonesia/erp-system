<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TargetPegawai
{
    /**
     * ATURAN: Selalu berikan kode nya full 1 file jangan hanya sebagian.
     */
    public static function produksiRotary2(
        string $table_name,
        string $table_hasil,
        string $customQueryId,
        int $produksiId,
        string $keyJumlah,
    ) {
        // 1. Ambil data utama produksi untuk mendapatkan id_mesin
        $data_main = DB::table($table_name)
            ->join("mesins", "mesins.id", "=", "$table_name.id_mesin")
            ->where("$table_name.id", $produksiId) // Spesifik merujuk ke tabel utama
            ->selectRaw("
                $table_name.id_mesin,
                $table_name.id,
                $table_name.tgl_produksi,
                mesins.nama_mesin
            ")
            ->first();
        if (!$data_main) return collect([]);

        $id_mesin = $data_main->id_mesin;
        $nama_mesin = $data_main->nama_mesin;
        $tgl_produksi = $data_main->tgl_produksi ?? null;

        $isPast = false;
        $isFriday = false;
        if ($tgl_produksi) {
            $prodDate = \Carbon\Carbon::parse($tgl_produksi);
            $isPast = $prodDate->lt(today());
            $isFriday = $prodDate->isFriday();
        }

        // 2. Definisi SQL Ukuran agar seragam
        $ukuranSql = "CONCAT(
            TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
            TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
            TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
        )";

        // 3. Ambil data akumulasi produksi
        $progressMesin = DB::table($table_name)
            ->join($table_hasil, "$table_hasil.id_produksi", "=", "$table_name.id")
            ->join("ukurans", "ukurans.id", "=", "$table_hasil.id_ukuran")
            ->where("{$table_hasil}.{$customQueryId}", $produksiId)
            ->selectRaw("
                $table_hasil.id_ukuran AS id_ukuran,
                $ukuranSql AS ukuran_formatted,
                SUM(CAST($table_hasil.$keyJumlah AS UNSIGNED)) AS total
            ")
            ->groupBy('id_ukuran')
            ->get()
            ->map(function ($rowProduksi) use ($id_mesin, $nama_mesin, $ukuranSql, $isPast, $isFriday) {
                
                // 4. Cari target (Langsung gunakan first tanpa map tambahan agar objek tetap utuh)
                $targetData = DB::table("targets")
                    ->join("mesins", "mesins.id", "=", "targets.id_mesin")
                    ->join("ukurans", "ukurans.id", "=", "targets.id_ukuran")
                    ->where('targets.id_mesin', $id_mesin)
                    ->where("targets.id_ukuran", $rowProduksi->id_ukuran)
                    ->selectRaw("
                        $ukuranSql AS ukuran_formatted,
                        mesins.nama_mesin AS nama_mesin,
                        targets.target AS nilai_target,
                        targets.jam,
                        targets.jam_mulai,
                        targets.jam_selesai
                    ")
                    ->first();

                // 5. Kalkulasi
                $targetValue = $targetData ? ($targetData->nilai_target ?? 0) : 0;
                $progress = 0;
                $targetSaatIni = 0;

                if ($targetValue > 0 && $targetData) {
                    $jamMulaiStr = $targetData->jam_mulai ?? '10:00:00';
                    $jamSelesaiStr = $targetData->jam_selesai ?? '16:00:00';

                    $startTime = now()->setTimeFromTimeString($jamMulaiStr);
                    $endTime = now()->setTimeFromTimeString($jamSelesaiStr);

                    // If it is Friday, adjust target and end time by subtracting 2 hours
                    if ($isFriday) {
                        $targetJam = (int) ($targetData->jam ?? 10);
                        if ($targetJam > 2) {
                            $targetPerJam = $targetValue / $targetJam;
                            $targetValue = $targetValue - (2 * $targetPerJam);
                        }
                        $endTime->subHours(2);
                    }

                    if ($endTime->lt($startTime)) {
                        // Overnight shift
                        if (now()->format('H:i:s') <= $endTime->format('H:i:s')) {
                            $startTime->subDay();
                        } else {
                            $endTime->addDay();
                        }
                    }

                    if ($isPast) {
                        // Past production: target is the full target value
                        $targetSaatIni = $targetValue;
                        $progress = min(round(($rowProduksi->total / $targetSaatIni) * 100, 1), 100);
                        if ($progress >= 100 && $rowProduksi->total < $targetSaatIni) {
                            $progress = 99.9;
                        }
                    } else {
                        // Today's/current production: calculate proportional target based on elapsed time
                        $totalDuration = $endTime->diffInSeconds($startTime, true);
                        if ($totalDuration <= 0) {
                            $totalDuration = 1; // prevent division by zero
                        }

                        if (now()->lt($startTime)) {
                            $elapsed = 0;
                        } elseif (now()->gt($endTime)) {
                            $elapsed = $totalDuration;
                        } else {
                            $elapsed = now()->diffInSeconds($startTime, true);
                        }

                        $targetSaatIniRaw = ($elapsed / $totalDuration) * $targetValue;
                        $targetSaatIni = (int) round($targetSaatIniRaw);

                        if ($targetSaatIni > 0) {
                            $progress = min(round(($rowProduksi->total / $targetSaatIni) * 100, 1), 100);
                            if ($progress >= 100 && $rowProduksi->total < $targetSaatIni) {
                                $progress = 99.9;
                            }
                        } else {
                            $progress = $rowProduksi->total > 0 ? 100 : 0;
                        }
                    }
                }

                // 6. Return Data (Pastikan key sesuai dengan hasil query targetData)
                return [
                    "ukuran" => $targetData ? ($targetData->ukuran_formatted ?? "Ukuran tdk terdaftar") : "Ukuran tdk terdaftar",
                    "nama_mesin" => $nama_mesin,
                    "ukuran_formatted" => $rowProduksi->ukuran_formatted,
                    "id_ukuran" => $rowProduksi->id_ukuran,
                    "target" => $targetValue,
                    "target_saat_ini" => $targetSaatIni,
                    "progress" => $progress,
                    "total_produksi" => $rowProduksi->total,
                ];
            });

        return $progressMesin;
    }

}