<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_barang')->constrained('jenis_barang');
            $table->decimal('panjang', 8, 2);
            $table->decimal('lebar',   8, 2);
            $table->decimal('tebal',   6, 2);
            $table->string('kw_grade')->nullable();

            $table->unsignedSmallInteger('jumlah_palet')->default(1);
            $table->integer('stok_lembar')->default(0);            // total lembar keluar
            $table->decimal('stok_kubikasi', 15, 6)->default(0);   // total m3 keluar

            $table->string('tujuan', 100);
            $table->foreignId('dikeluarkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mutasi_keluar')
                ->constrained('platform_jadi_mutasi_keluars')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_palet');
            $table->integer('jumlah_lembar')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_jadi_mutasi_keluar_palets');
        Schema::dropIfExists('platform_jadi_mutasi_keluars');
    }
};