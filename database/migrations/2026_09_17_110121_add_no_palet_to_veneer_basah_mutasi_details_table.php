<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veneer_basah_mutasi_details', function (Blueprint $table) {
            // Nomor urut palet dalam satu transaksi keluar (1, 2, 3, dst).
            // Sebelum ini, 1 kali "Catat Barang Keluar" dgn beberapa palet
            // digabung jadi 1 baris detail — sekarang tiap palet = 1 baris
            // detail sendiri, supaya sisi produksi (Dryer/Kedi) menerima
            // per-palet dan Gudang bisa menampilkan badge P1/P2/dst.
            $table->unsignedInteger('no_palet')->nullable()->after('id_veneer_basah_mutasi');
        });
    }

    public function down(): void
    {
        Schema::table('veneer_basah_mutasi_details', function (Blueprint $table) {
            $table->dropColumn('no_palet');
        });
    }
};