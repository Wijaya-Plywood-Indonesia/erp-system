<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->foreignId('id_hasil_terima_gudang_satu')
                ->nullable()
                ->after('id_produksi_terima_gudang_satu')
                ->constrained('hasil_terima_gudang_satu')
                ->nullOnDelete();

            $table->foreignId('id_produksi_nyusup')
                ->nullable()
                ->after('id_hasil_terima_gudang_satu')
                ->constrained('produksi_nyusup')
                ->nullOnDelete();

            $table->string('tujuan')->nullable()->after('id_produksi_nyusup');
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_terima_gudang_satu']);
            $table->dropForeign(['id_produksi_nyusup']);
            $table->dropColumn(['id_hasil_terima_gudang_satu', 'id_produksi_nyusup', 'tujuan']);
        });
    }
};
