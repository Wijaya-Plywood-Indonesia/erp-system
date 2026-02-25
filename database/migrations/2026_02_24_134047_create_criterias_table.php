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
        Schema::create('criterias', function (Blueprint $table) {
            $table->id();

            // FK ke tabel kategori_barang yang sudah ada
            $table->unsignedBigInteger('id_kategori_barang');

            // Teks pertanyaan yang tampil ke pengawas
            $table->string('nama_kriteria', 200);

            // Kode unik untuk identifikasi internal (ex: OPEN_SPLIT)
            $table->string('kode_kriteria', 50)->unique();

            // Emoji icon untuk mempercantik UI
            $table->string('icon_emoji', 10)->default('🔍');

            // Deskripsi/petunjuk tambahan di bawah pertanyaan
            $table->text('deskripsi')->nullable();

            // Urutan tampil pertanyaan (1, 2, 3, ...)
            $table->unsignedInteger('urutan')->default(0);

            // Bobot kepentingan kriteria ini (untuk future weighting)
            $table->decimal('bobot', 5, 2)->default(1.00);

            // Bisa di-toggle aktif/nonaktif tanpa hapus data
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index untuk query cepat
            $table->index(['id_kategori_barang', 'is_active', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('criterias');
    }
};
