<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nama_barang');
            $table->string('satuan');           // free text, disarankan lewat datalist dari yang sudah ada
            $table->string('kategori')->nullable(); // opsional, bebas diisi/dikosongkan
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique('nama_barang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang_umum');
    }
};