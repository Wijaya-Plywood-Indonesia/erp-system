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
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {

            // hapus foreign key lama
            $table->dropForeign(['id_produksi']);

            // buat foreign key baru
            $table->foreign('id_produksi')
                ->references('id')
                ->on('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {

            $table->dropForeign(['id_produksi']);

            $table->foreign('id_produksi')
                ->references('id')
                ->on('jenis_kayus');
        });
    }
};
