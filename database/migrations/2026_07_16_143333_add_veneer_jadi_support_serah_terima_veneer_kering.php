<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->foreignId('id_mutasi_keluar_palet_jadi')
                ->nullable()
                ->after('id_mutasi_keluar_palet')
                ->constrained('veneer_jadi_mutasi_keluar_palets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_mutasi_keluar_palet_jadi');
        });
    }
};
