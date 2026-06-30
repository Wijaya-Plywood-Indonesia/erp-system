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
        Schema::create('detail_absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_absensi')
                ->constrained('absensis')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('kode_pegawai')->index();
            // Simpan waktu lengkap (Y-m-d H:i:s)
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->date('tanggal'); // Relasi ke file log

            $table->unique(['kode_pegawai', 'tanggal']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_absensis');
    }
};
