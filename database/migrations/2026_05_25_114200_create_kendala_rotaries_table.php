<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kendala_rotaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_rotary_id')->constrained('produksi_rotaries')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            
            $table->dateTime('waktu_mulai');
            $table->text('kendala');
            $table->string('foto_kendala');
            
            $table->dateTime('waktu_selesai')->nullable();
            $table->string('foto_selesai')->nullable();
            
            $table->string('status')->default('pending'); // 'pending' atau 'selesai'
            $table->integer('durasi_menit')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendala_rotaries');
    }
};
