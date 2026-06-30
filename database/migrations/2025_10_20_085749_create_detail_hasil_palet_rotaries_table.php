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
        Schema::create('detail_hasil_palet_rotaries', function (Blueprint $table) {
            $table->id();

            // Relasi ke produksi_rotaries
            $table->foreignId('id_produksi')
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Relasi ke penggunaan_lahan_rotaries
            $table->foreignId('id_penggunaan_lahan')
                ->nullable()
                ->constrained('penggunaan_lahan_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->dateTime('timestamp_laporan');

            // Relasi ke ukurans
            $table->foreignId('id_ukuran')
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('kw');
            $table->integer('palet')->nullable();
            $table->integer('total_lembar')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_hasil_palet_rotaries');
    }
};
