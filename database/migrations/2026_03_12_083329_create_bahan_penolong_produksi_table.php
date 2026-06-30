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
        Schema::create('bahan_penolong_produksi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_bahan_penolong')->nullable();
            $table->string('satuan')->nullable();
            $table->string('kategori_produksi')->nullable();
            $table->decimal('harga', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bahan_penolong_produksi');
    }
};
