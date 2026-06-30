<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('riwayat_kayus', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_masuk');
            $table->date('tanggal_digunakan'); // diperbaiki
            $table->date('tanggal_habis');

            $table->foreignId('id_tempat_kayu')
                ->nullable()
                ->constrained('tempat_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_rotary')
                ->nullable()
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kayus');
    }
};
