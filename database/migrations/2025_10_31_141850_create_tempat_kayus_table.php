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
        Schema::create('tempat_kayus', function (Blueprint $table) {
            $table->id();

            $table->integer('jumlah_batang');
            $table->integer('poin');

            // Relasi ke kayu_masuks
            $table->foreignId('id_kayu_masuk')
                ->nullable()
                ->constrained('kayu_masuks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Relasi tambahan ke lahans
            $table->foreignId('id_lahan')
                ->nullable()
                ->constrained('lahans')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tempat_kayus');
    }
};
