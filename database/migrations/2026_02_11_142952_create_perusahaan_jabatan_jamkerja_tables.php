<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ============================
        //  TABEL PERUSAHAAN
        // ============================
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('alamat');
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // ============================
        //  TABEL JABATAN (dengan jam kerja)
        // ============================
        Schema::create('jabatan_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perusahaan_id')
                ->constrained('perusahaan')
                ->cascadeOnDelete();

            $table->string('nama_jabatan');
            $table->text('deskripsi')->nullable();

            // jam kerja langsung nempel disini
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->time('istirahat_mulai')->nullable();
            $table->time('istirahat_selesai')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_perusahaan');
        Schema::dropIfExists('perusahaan');
    }
};
