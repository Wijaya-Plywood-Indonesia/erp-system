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
        Schema::create('platform_jadi_mutasi_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_platform_jadi_mutasi')->constrained('platform_jadi_mutasis')->cascadeOnDelete();
            $t->foreignId('id_ukuran')->constrained('ukurans');
            // NB: stok_platform_jadi memakai id_jenis_barang (bukan id_jenis_kayu),
            // lihat migration update_platform_jadi_tables_use_jenis_barang.
            $t->foreignId('id_jenis_barang')->constrained('jenis_barang');
            $t->string('kw_grade');
            $t->integer('qty');
            $t->decimal('m3', 12, 6);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_jadi_mutasi_details');
    }
};
