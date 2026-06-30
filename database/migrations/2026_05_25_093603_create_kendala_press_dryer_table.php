<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kendala_press_dryers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_press_dryer_id')->constrained('produksi_press_dryers')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            
            $table->dateTime('waktu_mulai');
            $table->text('kendala');
            $table->string('foto_kendala');
            
            $table->dateTime('waktu_selesai')->nullable();
            $table->string('foto_selesai')->nullable();
            
            $table->string('status')->default('pending'); // 'pending' (berkendala) atau 'selesai'
            $table->integer('durasi_menit')->nullable(); // Opsional: Untuk kemudahan laporan downtime
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendala_press_dryers');
    }
};