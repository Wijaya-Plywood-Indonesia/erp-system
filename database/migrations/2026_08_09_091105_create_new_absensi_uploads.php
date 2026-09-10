<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_absensi_uploads', function (Blueprint $table) {
            $table->id();

            // Tanggal termuda yang ditemukan di dalam isi file yang diupload.
            // Cuma dipakai sebagai label/filter riwayat, bukan penentu tanggal
            // mana yang diproses (semua tanggal di dalam file tetap diproses).
            $table->date('tanggal');

            // Array path file (bisa lebih dari 1 file / mesin dalam 1 batch),
            // disimpan sebagai JSON. Contoh:
            // ["absensi-logs/GLogData_ (2).txt", "absensi-logs/wahana.txt"]
            $table->json('file_path');

            $table->string('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_absensi_uploads');
    }
};
