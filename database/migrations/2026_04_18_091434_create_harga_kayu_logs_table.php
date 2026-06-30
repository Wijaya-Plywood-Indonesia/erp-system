<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harga_kayu_logs', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel utama HargaKayu
            $table->foreignId('id_harga_kayu')->constrained('harga_kayus')->cascadeOnDelete();

            // Kolom untuk menyimpan snapshot harga
            $table->bigInteger('harga_lama')->default(0)->nullable();
            $table->bigInteger('harga_baru')->default(0)->nullable();

            // Audit Trail
            $table->string('petugas'); // Nama user yang melakukan aksi
            $table->string('aksi');    // 'Setujui', 'Tolak', atau 'Inisiasi'

            $table->timestamps(); // Mencatat waktu kejadian secara otomatis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harga_kayu_logs');
    }
};
