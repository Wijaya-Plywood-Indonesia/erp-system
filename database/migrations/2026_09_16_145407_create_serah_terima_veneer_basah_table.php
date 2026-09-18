<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_veneer_basah', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_veneer_basah_mutasi_detail')
                ->constrained('veneer_basah_mutasi_details')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->enum('tujuan', ['dryer', 'kedi']);

            $table->foreignId('id_produksi_dryer')
                ->nullable()
                ->constrained('produksi_press_dryers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('id_produksi_kedi')
                ->nullable()
                ->constrained('produksi_kedi')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('diserahkan_oleh');
            $table->string('diterima_oleh')->default('-');

            $table->string('ditolak_oleh')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('ditolak_at')->nullable();

            // Menunggu | Diterima | Ditolak
            $table->string('status')->default('Menunggu');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_veneer_basah');
    }
};
