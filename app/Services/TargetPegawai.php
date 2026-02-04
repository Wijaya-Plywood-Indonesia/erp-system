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
                mesins.nama_mesin
            ")
            ->first();
        if (!$data_main) return collect([]);

        $id_mesin = $data_main->id_mesin;
        $nama_mesin = $data_main->nama_mesin;

        // 2. Definisi SQL Ukuran agar seragam
        $ukuranSql = "CONCAT(
            TRIM(TRAILING '.00' FROM CAST(ukurans.panjang AS CHAR)), ' x ',
            TRIM(TRAILING '.00' FROM CAST(ukurans.lebar AS CHAR)), ' x ',
            TRIM(TRAILING '0' FROM TRIM(TRAILING '.' FROM CAST(ukurans.tebal AS CHAR)))
        )";

        // 3. Ambil data akumulasi produksi
        $progressMesin = DB::table($table_name)
            ->join($table_hasil, "$table_hasil.id_produksi", "=", "$table_name.id")
            ->join("penggunaan_lahan_rotaries", "$table_hasil.id_penggunaan_lahan", "=", "penggunaan_lahan_rotaries.id")
            ->join("jenis_kayus", "jenis_kayus.id", "=", "penggunaan_lahan_rotaries.id_jenis_kayu")
            ->join("ukurans", "ukurans.id", "=", "$table_hasil.id_ukuran")

            ->where("{$table_hasil}.{$customQueryId}", $produksiId)
            ->selectRaw("
                penggunaan_lahan_rotaries.id_jenis_kayu AS id_kayu,
                $table_hasil.id_ukuran AS id_ukuran,

                $ukuranSql AS ukuran_formatted,
                jenis_kayus.nama_kayu AS nama_kayu,
                SUM(CAST($table_hasil.$keyJumlah AS UNSIGNED)) AS total
            ")
            ->groupBy('id_kayu', 'id_ukuran', 'nama_kayu')
            ->get()
            ->map(function ($rowProduksi) use ($id_mesin,$nama_mesin, $ukuranSql) {
                
                // 4. Cari target (Langsung gunakan first tanpa map tambahan agar objek tetap utuh)
                $targetData = DB::table("targets")
                    ->join("mesins", "mesins.id", "=", "targets.id_mesin")
                    ->join("ukurans", "ukurans.id", "=", "targets.id_ukuran")
                    ->where('targets.id_mesin', $id_mesin)
                    ->where("targets.id_jenis_kayu", $rowProduksi->id_kayu)
                    ->where("targets.id_ukuran", $rowProduksi->id_ukuran)
                    ->selectRaw("
                        $ukuranSql AS ukuran_formatted,
                        mesins.nama_mesin AS nama_mesin,
                        targets.target AS nilai_target
                    ")
                    ->first();

                // 5. Kalkulasi
                $targetValue = $targetData->nilai_target ?? 0;
                $progress = 0;

                if ($targetValue > 0) {
                    $progress = min(round(($rowProduksi->total / $targetValue) * 100, 1), 100);
                }

                // 6. Return Data (Pastikan key sesuai dengan hasil query targetData)
                return [
                    "ukuran" => $targetData->ukuran_formatted ?? "Ukuran tdk terdaftar",
                    "nama_mesin" => $nama_mesin,
                    "nama_kayu" => $rowProduksi->nama_kayu,
                    "ukuran_formatted" => $rowProduksi->ukuran_formatted,
                    "id_ukuran" => $rowProduksi->id_ukuran,
                    "target" => $targetValue,
                    "progress" => $progress,
                    "total_produksi" => $rowProduksi->total,
                ];
            });

        return $progressMesin;
    }

}