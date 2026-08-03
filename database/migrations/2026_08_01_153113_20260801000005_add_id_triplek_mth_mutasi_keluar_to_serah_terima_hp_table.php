<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->foreignId('id_triplek_mth_mutasi_keluar')
                ->nullable()
                ->after('id_platform_mth_mutasi_keluar')
                ->constrained('triplek_mth_mutasi_keluars')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_triplek_mth_mutasi_keluar');
        });
    }
};
