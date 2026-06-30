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
        Schema::create('barang_setengah_jadi_hp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_ukuran')
                ->nullable()
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_barang')
                ->nullable()
                ->constrained('jenis_barang')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_grade')
                ->nullable()
                ->constrained('grades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang_setengah_jadi_hp');
    }
};
