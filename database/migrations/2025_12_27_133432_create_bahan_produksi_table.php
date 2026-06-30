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
        Schema::create('bahan_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_joint')
                ->constrained('produksi_joint')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nama_bahan');
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bahan_produksi');
    }
};
