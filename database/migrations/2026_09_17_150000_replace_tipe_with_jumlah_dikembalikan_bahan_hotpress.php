<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PENGGANTI pendekatan sebelumnya (kolom `tipe`: pemakaian/pengembalian
     * yang berarti 1 baris baru dibuat setiap kali barang dikembalikan).
     *
     * Sekarang pengembalian TIDAK membuat baris baru — cukup menambah nilai
     * `jumlah_dikembalikan` pada baris pemakaian (`bahan_hotpress`) yang
     * sama. Sisa yang masih bisa dikembalikan dari 1 baris = isi -
     * jumlah_dikembalikan. Ini menghindari tabel Bahan Hot Press dipenuhi
     * baris "pengembalian" yang membingungkan.
     *
     * Migration ini aman dijalankan baik di database yang SUDAH sempat
     * menjalankan migrasi `tipe` sebelumnya, maupun yang belum sama sekali
     * (pakai pengecekan Schema::hasColumn).
     */
    public function up(): void
    {
        if (Schema::hasColumn('bahan_hotpress', 'tipe')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->dropColumn('tipe');
            });
        }

        if (! Schema::hasColumn('bahan_hotpress', 'jumlah_dikembalikan')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('isi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bahan_hotpress', 'jumlah_dikembalikan')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }

        if (! Schema::hasColumn('bahan_hotpress', 'tipe')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->enum('tipe', ['pemakaian', 'pengembalian'])->default('pemakaian')->after('sumber');
            });
        }
    }
};
