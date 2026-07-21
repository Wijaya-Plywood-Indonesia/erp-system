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
        Schema::table('bahan_terima_gudang_satu', function (Blueprint $table) {
            $table->foreignId('id_hasil_terima_gudang_satu')
                ->nullable()
                ->after('id_produksi_terima_gudang_satu')
                ->constrained('hasil_terima_gudang_satu')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_terima_gudang_satu', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_terima_gudang_satu']);
            $table->dropColumn('id_hasil_terima_gudang_satu');
        });
    }
};
