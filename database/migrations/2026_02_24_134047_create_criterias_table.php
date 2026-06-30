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

            $table->string('nama_kriteria', 200);

            $table->text('deskripsi')->nullable();

            $table->unsignedInteger('urutan')->default(0);

            $table->decimal('bobot', 5, 2)->default(1.00);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

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
