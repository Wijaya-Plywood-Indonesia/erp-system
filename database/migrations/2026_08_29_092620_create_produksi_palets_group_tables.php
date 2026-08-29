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
        // 1. TABEL UTAMA: Produksi Palet (Header)
        Schema::create('produksi_palets', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // 2. TABEL DETAIL: Pegawai Palet (Absensi / Tim Kerja)
        Schema::create('pegawai_palets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_produksi_palet')
                ->constrained('produksi_palets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();

            $table->string('izin')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });

        // 3. TABEL DETAIL: Hasil Produksi Palet (Penggunaan Stok Log Core & Output Palet)
        Schema::create('hasil_produksi_palets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_produksi_palet')
                ->constrained('produksi_palets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_pegawai_palet')
                ->constrained('pegawai_palets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_stok_log_core')
                ->constrained('stok_log_cores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('modal')->nullable();
            $table->integer('hasil')->nullable();
            $table->timestamps();
        });

        // 4. TABEL DETAIL: Validasi Produksi Palet (Approval/Status)
        Schema::create('validasi_produksi_palets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_produksi_palet')
                ->constrained('produksi_palets')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('status');
            $table->string('role');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop dengan urutan terbalik dari penyiapan untuk menghindari error Foreign Key Constraint
        Schema::dropIfExists('validasi_produksi_palets');
        Schema::dropIfExists('hasil_produksi_palets');
        Schema::dropIfExists('pegawai_palets');
        Schema::dropIfExists('produksi_palets');
    }
};
