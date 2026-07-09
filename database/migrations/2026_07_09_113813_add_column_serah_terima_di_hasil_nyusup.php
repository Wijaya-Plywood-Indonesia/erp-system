<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_barang_dikerjakan', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_gudang_satu')
                ->nullable()
                ->after('id_pegawai_nyusup')
                ->constrained('serah_terima_gudang_satu')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detail_barang_dikerjakan', function (Blueprint $table) {
            $table->dropForeign(['id_serah_terima_gudang_satu']);
            $table->dropColumn('id_serah_terima_gudang_satu');
        });
    }
};
