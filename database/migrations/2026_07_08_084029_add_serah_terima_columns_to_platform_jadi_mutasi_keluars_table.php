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
        Schema::table('platform_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->foreignId('diterima_by')
                ->nullable()
                ->after('dikeluarkan_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('diterima_at')
                ->nullable()
                ->after('diterima_by');
            $table->foreignId('id_produksi_hp')
                ->nullable()
                ->after('diterima_at')
                ->constrained('produksi_hp')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_produksi_hp');
            $table->dropColumn('diterima_at');
            $table->dropConstrainedForeignId('diterima_by');
        });
    }
};
