<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->foreignId('id_hasil_nyusup')
                ->nullable()
                ->after('id_hasil_terima_gudang_satu')
                ->constrained('detail_barang_dikerjakan')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_nyusup']);
            $table->dropColumn('id_hasil_nyusup');
        });
    }
};
