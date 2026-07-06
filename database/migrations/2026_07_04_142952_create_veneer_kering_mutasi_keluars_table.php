<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veneer_kering_mutasi_keluars', function (Blueprint $table) {
            $table->id();

            // Pakai id_ukuran (FK) bukan panjang/lebar/tebal denormalized,
            // supaya konsisten dengan konvensi tabel Kering lainnya
            // (VeneerMutasiDetail, StokVeneerKering, dll semuanya pakai
            // id_ukuran + relasi ke tabel ukurans).
            $table->foreignId('id_ukuran')->constrained('ukurans');
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->string('kw', 10)->nullable();

            $table->unsignedSmallInteger('jumlah_palet');
            $table->decimal('qty', 12, 4)->default(0);   // total lembar keluar
            $table->decimal('m3', 12, 6)->default(0);    // total volume keluar

            $table->string('tujuan_keluar', 100)->default('Repair');
            $table->foreignId('dikeluarkan_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index(['id_ukuran', 'id_jenis_kayu', 'kw']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veneer_kering_mutasi_keluars');
    }
};