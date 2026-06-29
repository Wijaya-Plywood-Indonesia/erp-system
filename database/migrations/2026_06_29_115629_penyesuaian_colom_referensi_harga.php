<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Truncate dan recreate — jalankan manual truncate dulu sebelum migrate
        Schema::dropIfExists('referensi_harga_produksi');

        Schema::create('referensi_harga_produksi', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable();
            $table->unsignedBigInteger('id_ukuran')->nullable();
            $table->unsignedBigInteger('id_jenis_kayu')->nullable();
            $table->unsignedBigInteger('id_kategori_barang')->nullable();
            $table->unsignedBigInteger('id_grade')->nullable();
            $table->tinyInteger('kw_min')->unsigned()->nullable();
            $table->tinyInteger('kw_max')->unsigned()->nullable();
            $table->decimal('t_min', 8, 2)->nullable();
            $table->decimal('t_max', 8, 2)->nullable();
            $table->decimal('harga', 15, 2)->nullable();
            $table->unsignedBigInteger('id_sub_anak_akun')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referensi_harga_produksi');
    }
};
