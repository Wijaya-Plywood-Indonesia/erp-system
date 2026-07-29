<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_barang_umum', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_barang_umum')->constrained('barang_umum')->cascadeOnDelete();
            $table->decimal('stok_qty', 15, 4)->default(0);

            // Kolom nilai Rp: tetap ada di tabel, disembunyikan dari UI untuk saat ini
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai_stok', 15, 2)->default(0);

            $table->foreignId('id_last_log')->nullable();
            $table->timestamps();

            $table->unique('id_barang_umum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_barang_umum');
    }
};