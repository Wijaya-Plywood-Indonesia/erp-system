<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            // Pola sama seperti id_produksi_hp: nullable, diisi belakangan
            // saat barang ini "diterima" di sisi Produksi Repair.
            $table->foreignId('id_produksi_repair')
                ->nullable()
                ->after('id_produksi_hp')
                ->constrained('produksi_repairs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->dropForeign(['id_produksi_repair']);
            $table->dropColumn('id_produksi_repair');
        });
    }
};
