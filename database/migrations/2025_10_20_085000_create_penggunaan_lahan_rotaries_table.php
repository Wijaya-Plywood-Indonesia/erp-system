<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('penggunaan_lahan_rotaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_lahan')
                ->constrained('lahans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_produksi')
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_kayu')
                ->nullable()
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('jumlah_batang')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggunaan_lahan_rotaries');
    }
};