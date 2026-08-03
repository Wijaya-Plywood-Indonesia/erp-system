<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pengajuan_barang')->constrained('pengajuan_barang')->cascadeOnDelete();
            $table->foreignId('id_barang_umum')->constrained('barang_umum');
            $table->decimal('jumlah', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_barang_detail');
    }
};