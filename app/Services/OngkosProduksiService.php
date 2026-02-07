<?php

namespace App\Services;

use App\Models\ProduksiRotary;
use Illuminate\Container\Attributes\DB;

class OngkosProduksiService
{
    public function getQueryGrouped()
    {
        // Mengambil data unik berdasarkan kelompok mesin
        // Kita gunakan join ke tabel mesin untuk mempermudah filter
        return ProduksiRotary::query()
            ->join('mesins', 'produksi_rotaries.id_mesin', '=', 'mesins.id')
            ->select('mesins.nama_mesin', DB::raw('MIN(produksi_rotaries.id) as id'))
            ->groupBy('mesins.nama_mesin');
    }

    // Pengelompokan nama mesin
    public function getKelompokMesin($namaMesin): string
    {
        $namaMesinLower = strtolower($namaMesin);

        // Jika mesin adalah sanji atau meranti, kelompokkan ke 260
        if (str_contains($namaMesinLower, 'spindless') || str_contains($namaMesinLower, 'meranti')) {
            return '260';
        }

        // Contoh pengelompokan ke 130 (silahkan sesuaikan nama mesinnya)
        if (str_contains($namaMesinLower, 'sanji') || str_contains($namaMesinLower, 'yuequn') || str_contains($namaMesinLower, 'yuequn')) {
            return '130';
        }

        return $namaMesin; // Default jika tidak masuk kategori
    }
}
