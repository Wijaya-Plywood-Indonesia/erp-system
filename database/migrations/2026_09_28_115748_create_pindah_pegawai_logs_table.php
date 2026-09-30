<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pindah_pegawai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 50);               // kode lini asal, mis. 'repair'
            $table->unsignedBigInteger('id_sumber');    // id baris pegawai di lini asal
            $table->string('tujuan', 50);               // kode lini tujuan, mis. 'press_dryer'
            $table->string('model_tujuan');             // class model baris pegawai tujuan
            $table->unsignedBigInteger('id_tujuan');    // id baris pegawai yang dibuat di tujuan
            $table->unsignedBigInteger('id_pegawai');
            $table->date('tanggal');
            $table->unsignedInteger('durasi_menit');
            $table->time('pulang_lama');
            $table->time('pulang_baru');
            $table->text('ket_sumber_lama')->nullable(); // keterangan sumber sebelum ditambah catatan pindah
            $table->timestamp('dibatalkan_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['sumber', 'id_sumber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pindah_pegawai_logs');
    }
};