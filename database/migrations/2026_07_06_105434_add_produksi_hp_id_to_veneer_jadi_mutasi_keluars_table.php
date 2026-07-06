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
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->foreignId('id_produksi_hp')
                ->nullable()
                ->after('id')
                ->constrained('produksi_hp')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->dropForeign(['id_produksi_hp']);
            $table->dropColumn('id_produksi_hp');
        });
    }
};
