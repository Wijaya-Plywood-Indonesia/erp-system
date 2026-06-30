<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. kendala_hps
        Schema::create('kendala_hps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_hp_id')->constrained('produksi_hp')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            
            $table->dateTime('waktu_mulai');
            $table->text('kendala');
            $table->string('foto_kendala');
            
            $table->dateTime('waktu_selesai')->nullable();
            $table->string('foto_selesai')->nullable();
            
            $table->string('status')->default('pending');
            $table->integer('durasi_menit')->nullable();
            
            $table->timestamps();
        });

        // 2. kendala_kedis
        Schema::create('kendala_kedis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_kedi_id')->constrained('produksi_kedi')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            
            $table->dateTime('waktu_mulai');
            $table->text('kendala');
            $table->string('foto_kendala');
            
            $table->dateTime('waktu_selesai')->nullable();
            $table->string('foto_selesai')->nullable();
            
            $table->string('status')->default('pending');
            $table->integer('durasi_menit')->nullable();
            
            $table->timestamps();
        });

        // 3. kendala_sandings
        Schema::create('kendala_sandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_sanding_id')->constrained('produksi_sandings')->cascadeOnDelete();
            $table->foreignId('mesin_id')->constrained('mesins')->cascadeOnDelete();
            
            $table->dateTime('waktu_mulai');
            $table->text('kendala');
            $table->string('foto_kendala');
            
            $table->dateTime('waktu_selesai')->nullable();
            $table->string('foto_selesai')->nullable();
            
            $table->string('status')->default('pending');
            $table->integer('durasi_menit')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendala_sandings');
        Schema::dropIfExists('kendala_kedis');
        Schema::dropIfExists('kendala_hps');
    }
};
