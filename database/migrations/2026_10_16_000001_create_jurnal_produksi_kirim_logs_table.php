<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_produksi_kirim_logs', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 50);
            $table->date('tanggal');
            $table->string('no_dokumen', 100);
            // terkirim | diperbarui | ditolak | gagal
            $table->string('status', 20);
            $table->char('payload_hash', 40)->nullable();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_kredit', 18, 2)->default(0);
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->text('pesan')->nullable();
            $table->unsignedBigInteger('dikirim_oleh')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'tanggal']);
            $table->index('no_dokumen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_produksi_kirim_logs');
    }
};
