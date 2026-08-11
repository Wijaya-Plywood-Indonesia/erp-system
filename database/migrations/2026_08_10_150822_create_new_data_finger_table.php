<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_data_finger', function (Blueprint $table) {
            $table->id();

            // Batch upload mana yang berkontribusi ke jam_masuk vs jam_pulang.
            // Dipisah supaya tetap bisa ditelusuri walau row ini hasil merge dari
            // beberapa kali upload / beberapa mesin yang berbeda.
            $table->foreignId('id_absensi_masuk')
                ->nullable()
                ->constrained('new_absensi_uploads')
                ->nullOnDelete();

            $table->foreignId('id_absensi_pulang')
                ->nullable()
                ->constrained('new_absensi_uploads')
                ->nullOnDelete();

            $table->string('kode_pegawai');
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();

            $table->timestamps();

            // Satu pegawai hanya boleh punya 1 row per tanggal.
            // Row baru dari upload berikutnya akan MERGE (update MIN/MAX),
            // bukan insert baru, kalau kombinasi ini sudah ada.
            $table->unique(['kode_pegawai', 'tanggal']);

            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_data_finger');
    }
};
