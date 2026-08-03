<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->foreignId('id_platform_mth_mutasi_keluar')
                ->nullable()
                ->after('id_produksi_sanding')
                ->constrained('platform_mth_mutasi_keluars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_platform_mth_mutasi_keluar');
        });
    }
};
