<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->enum('sumber', ['veneer', 'platform'])->nullable()->after('id_barang_setengah_jadi');
            $table->foreignId('id_mutasi_keluar_platform')->nullable()->after('id_mutasi_keluar_palet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->dropColumn(['sumber', 'id_mutasi_keluar_platform']);
        });
    }
};
