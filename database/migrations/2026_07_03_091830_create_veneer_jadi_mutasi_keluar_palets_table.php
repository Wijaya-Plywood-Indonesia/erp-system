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
        Schema::create('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mutasi_keluar')
                ->constrained('veneer_jadi_mutasi_keluars')
                ->cascadeOnDelete();
            $table->integer('nomor_palet');
            $table->integer('jumlah_lembar');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veneer_jadi_mutasi_keluar_palets');
    }
};
