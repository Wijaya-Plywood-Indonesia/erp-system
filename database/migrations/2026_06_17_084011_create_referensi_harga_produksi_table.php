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
        Schema::create('referensi_harga_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_ukuran')->constrained('ukurans')->cascadeOnDelete();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();
            $table->string('jenis_barang', 100);
            $table->string('kw', 50);
            $table->decimal('harga', 12, 4);
            $table->timestamps();

            $table->unique(['id_ukuran', 'id_jenis_kayu', 'jenis_barang', 'kw'], 'ref_harga_prod_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('referensi_harga_produksi');
        Schema::enableForeignKeyConstraints();
    }
};
