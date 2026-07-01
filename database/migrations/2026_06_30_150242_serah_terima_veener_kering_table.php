<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->id();

            // Sumber dari Dryer
            $table->foreignId('id_detail_hasil')
                ->nullable()
                ->constrained('detail_hasils')
                ->nullOnDelete();

            // Sumber dari Kedi
            $table->foreignId('id_detail_bongkar_kedi')
                ->nullable()
                ->constrained('detail_bongkar_kedi')
                ->nullOnDelete();

            // Pembeda sumber — wajib diisi, tidak boleh null
            $table->enum('tipe_sumber', ['dryer', 'kedi']);

            // Tujuan: produksi Repair yang menerima, nullable selama belum diambil
            $table->unsignedBigInteger('id_produksi_repair')->nullable();
            $table->foreign('id_produksi_repair')
                ->references('id')->on('produksi_repairs')
                ->nullOnDelete();

            $table->string('diserahkan_oleh');
            $table->string('diterima_oleh')->default('-');
            $table->string('status')->default('Serah Veneer');
            // Status: 'Serah Veneer' | 'Terima Veneer'

            $table->timestamps();

            // Index untuk query yang sering dipakai
            $table->index('tipe_sumber');
            $table->index('diterima_oleh');
            $table->index('id_produksi_repair');

            // Pastikan 1 detail_hasil / 1 detail_bongkar_kedi hanya bisa diserahkan sekali
            $table->unique('id_detail_hasil');
            $table->unique('id_detail_bongkar_kedi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_veneer_kering');
    }
};
