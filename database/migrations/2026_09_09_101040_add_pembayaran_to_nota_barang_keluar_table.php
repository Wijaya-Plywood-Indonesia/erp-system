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
        Schema::table('nota_barang_keluar', function (Blueprint $table) {
            $table->string('metode_pembayaran', 50)->nullable()->after('tujuan_nota');
            $table->foreignId('id_rekening_perusahaan')
                ->nullable()
                ->after('metode_pembayaran')
                ->constrained('rekening_perusahaan')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_barang_keluar', function (Blueprint $table) {
            $table->dropForeign(['id_rekening_perusahaan']);
            $table->dropColumn(['metode_pembayaran', 'id_rekening_perusahaan']);
        });
    }
};
