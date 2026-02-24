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
        Schema::create('grading_sessions', function (Blueprint $table) {
            $table->id();

            // Kategori produk yang sedang dinilai
            $table->unsignedBigInteger('id_kategori_barang');

            // Kode produk opsional (untuk identifikasi di lapangan)
            $table->string('kode_produk', 100)->nullable();

            // Pengawas yang melakukan grading
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Status alur sesi
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])
                ->default('in_progress');

            // Grade hasil rekomendasi sistem (diisi setelah inference selesai)
            $table->foreignId('hasil_grade_id')
                ->nullable()
                ->constrained('grades')
                ->nullOnDelete();

            // Persentase per grade dalam JSON
            // Contoh: {"UTY": 91.2, "BTR": 78.5, "B1": 63.0}
            $table->json('persentase_hasil')->nullable();

            // Teks alasan utama kenapa grade ini dipilih
            $table->text('alasan_utama')->nullable();

            // Berapa detik pengawas menyelesaikan grading ini
            $table->unsignedInteger('durasi_detik')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['id_kategori_barang', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_sessions');
    }
};
