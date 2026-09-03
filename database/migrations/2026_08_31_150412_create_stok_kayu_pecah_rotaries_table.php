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
        Schema::create('stok_kayu_pecah_rotaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();
            $table->integer('panjang');
            $table->integer('stok_batang')->default(0);
            $table->timestamps();

            $table->unique(['id_jenis_kayu', 'panjang'], 'unique_jenis_panjang');
        });

        Schema::create('log_stok_kayu_pecah_rotaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();
            $table->integer('panjang');
            $table->foreignId('id_lahan')->nullable()->constrained('lahans')->nullOnDelete();
            $table->enum('tipe', ['masuk', 'keluar']);
            $table->integer('jumlah_batang');
            $table->integer('stok_before');
            $table->integer('stok_after');
            $table->string('keterangan')->nullable();
            $table->nullableMorphs('referensi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_stok_kayu_pecah_rotaries');
        Schema::dropIfExists('stok_kayu_pecah_rotaries');
    }
};
