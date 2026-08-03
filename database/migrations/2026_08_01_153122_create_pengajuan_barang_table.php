<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_barang', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('lokasi_penggunaan'); // mis. "Gerbang", "Rotary", dll - bebas isi
            $table->text('keterangan')->nullable();
            $table->string('foto')->nullable();

            $table->foreignId('diajukan_oleh')->constrained('users');

            // Approval Kepala Produksi
            $table->enum('status_kepala_produksi', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->foreignId('disetujui_kepala_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_kepala_at')->nullable();

            // Approval Admin Barang
            $table->enum('status_admin_barang', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->foreignId('disetujui_admin_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_admin_at')->nullable();

            // Guard: kapan stok benar-benar dipotong (hanya sekali, saat keduanya disetujui)
            $table->timestamp('diproses_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barang');
    }
};