<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda penerimaan hasil sanding ke Gudang Platform Jadi.
 * Baris DIBUAT saat tombol Terima ditekan (satu baris per HasilSanding/palet).
 * Antrean "menunggu" = HasilSanding yang belum punya baris di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_platform_jadi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_hasil_sanding')
                ->unique() // satu palet hasil sanding hanya bisa diterima sekali
                ->constrained('hasil_sandings')
                ->cascadeOnDelete();
            $table->foreignId('diterima_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('diterima_at')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_platform_jadi');
    }
};