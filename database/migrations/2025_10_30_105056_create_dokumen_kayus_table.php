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
        Schema::create('dokumen_kayus', function (Blueprint $table) {
            $table->id();
            $table->string('nama_legal');
            $table->string('upload_ktp')->nullable();

            $table->string('dokumen_legal')->nullable();
            $table->string('upload_dokumen')->nullable();
            $table->string('no_dokumen_legal')->nullable();
            $table->string('foto_lokasi')->nullable();

            // Kolom untuk Maps
            $table->string('nama_tempat')->nullable();
            $table->text('alamat_lengkap')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_kayus');
    }
};
