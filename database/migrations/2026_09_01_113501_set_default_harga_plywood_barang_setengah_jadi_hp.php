<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('barang_setengah_jadi_hp')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->where('kategori_barang.nama_kategori', 'Plywood')
            ->whereNull('barang_setengah_jadi_hp.harga')
            ->update(['barang_setengah_jadi_hp.harga' => 200000]);
    }

    public function down(): void
    {
        DB::table('barang_setengah_jadi_hp')
            ->join('grades', 'grades.id', '=', 'barang_setengah_jadi_hp.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->where('kategori_barang.nama_kategori', 'Plywood')
            ->update(['barang_setengah_jadi_hp.harga' => null]);
    }
};
