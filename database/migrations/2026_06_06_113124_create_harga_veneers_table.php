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
        Schema::create('harga_veneers', function (Blueprint $table) {
            $table->id();
            $table->string('ukuran'); // e.g. faceback, face, back, core
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('harga_basah');
            $table->integer('harga_kering');
            $table->integer('harga_jadi');
            $table->timestamps();

            // Prevent duplicate records for same veneer type (ukuran) and wood type (jenis_kayu)
            $table->unique(['ukuran', 'id_jenis_kayu']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_veneers');
    }
};
