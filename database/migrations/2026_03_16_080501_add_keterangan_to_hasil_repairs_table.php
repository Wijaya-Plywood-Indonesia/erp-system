<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom keterangan untuk memperbaiki error "Unknown column hasil_repairs.keterangan".
     */
    public function up(): void
    {
        Schema::table('hasil_repairs', function (Blueprint $table) {
            // Kita gunakan if untuk memastikan tidak ada error "Duplicate column"
            if (!Schema::hasColumn('hasil_repairs', 'keterangan')) {
                // Gunakan text() jika keterangannya bisa panjang, atau string() jika pendek.
                // After 'jumlah' (asumsi kolom terakhir biasanya jumlah atau hasil)
                $table->text('keterangan')->nullable()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hasil_repairs', function (Blueprint $table) {
            if (Schema::hasColumn('hasil_repairs', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
        });
    }
};
