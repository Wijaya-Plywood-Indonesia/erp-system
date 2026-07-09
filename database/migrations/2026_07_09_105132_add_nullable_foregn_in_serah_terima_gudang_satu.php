<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_pilih_plywood']);
            $table->dropForeign(['id_produksi_terima_gudang_satu']);
        });

        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->unsignedBigInteger('id_hasil_pilih_plywood')->nullable()->change();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu')->nullable()->change();

            $table->foreign('id_hasil_pilih_plywood')
                ->references('id')->on('hasil_pilih_plywood')
                ->nullOnDelete();

            $table->foreign('id_produksi_terima_gudang_satu')
                ->references('id')->on('produksi_terima_gudang_satu')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_pilih_plywood']);
            $table->dropForeign(['id_produksi_terima_gudang_satu']);
        });

        Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->unsignedBigInteger('id_hasil_pilih_plywood')->nullable(false)->change();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu')->nullable(false)->change();

            $table->foreign('id_hasil_pilih_plywood')
                ->references('id')->on('hasil_pilih_plywood');

            $table->foreign('id_produksi_terima_gudang_satu')
                ->references('id')->on('produksi_terima_gudang_satu');
        });
    }
};
