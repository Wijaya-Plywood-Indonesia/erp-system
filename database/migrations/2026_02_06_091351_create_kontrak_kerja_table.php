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
        Schema::create('kontrak_kerja', function (Blueprint $table) {

            $table->id();

            // 🔹 Data Pegawai Snapshot
            $table->string('kode');                     // kode pegawai
            $table->string('nama');                     // nama pegawai
            $table->string('jenis_kelamin')->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->string('karyawan_di')->nullable();
            $table->string('alamat_perusahaan')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('nik')->nullable();
            $table->string('tempat_tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telepon')->nullable();

            // 🔹 Informasi Kontrak
            $table->date('kontrak_mulai')->nullable();
            $table->date('kontrak_selesai')->nullable();
            $table->integer('durasi_kontrak')->nullable();     // bulan atau hari
            $table->string('tanggal_kontrak')->nullable();
            $table->string('no_kontrak')->nullable();

            // 🔹 Status Dokumen & Penanggung Jawab
            $table->enum('status_dokumen', ['draft', 'dicetak', 'ditandatangani'])
                ->default('draft');
            $table->string('bukti_ttd')->nullable();

            // 🔹 Orang yang bertanggung jawab
            $table->string('dibuat_oleh')->nullable();
            $table->string('divalidasi_oleh')->nullable();

            // 🔹 Status Kontrak Sistem (untuk notifikasi)
            $table->enum('status_kontrak', ['active', 'soon', 'expired'])
                ->default('active');

            $table->text('keterangan')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontrak_kerja');
    }
};
