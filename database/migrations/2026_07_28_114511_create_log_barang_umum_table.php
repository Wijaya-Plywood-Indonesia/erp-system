<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_barang_umum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_barang_umum')->constrained('barang_umum')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('tipe_transaksi', ['masuk', 'keluar']);
            $table->string('keterangan')->nullable();

            // Untuk transaksi otomatis dari modul lain (opsional, boleh null untuk input manual)
            $table->string('referensi_type')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();

            $table->decimal('qty', 15, 4);

            // Kolom nilai Rp: tetap ada, disembunyikan dari UI untuk saat ini
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai', 15, 2)->default(0);

            $table->decimal('stok_qty_before', 15, 4);
            $table->decimal('nilai_stok_before', 15, 2)->default(0);
            $table->decimal('stok_qty_after', 15, 4);
            $table->decimal('nilai_stok_after', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['referensi_type', 'referensi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_barang_umum');
    }
};