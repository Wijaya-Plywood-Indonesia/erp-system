<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isi_palet_veneers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')->nullable()->constrained('jenis_kayus')->nullOnDelete();
            $table->foreignId('id_ukuran')->constrained('ukurans')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_lembar');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isi_palet_veneers');
    }
};
