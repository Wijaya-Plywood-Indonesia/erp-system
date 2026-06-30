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
        Schema::create('hasil_pilih_veneer_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_hasil_pilih_veneer')->constrained('hasil_pilih_veneer')->cascadeOnDelete();
    $table->foreignId('id_pegawai_pilih_veneer')->constrained('pegawai_pilih_veneer')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_pilih_veneer_pegawai');
    }
};
