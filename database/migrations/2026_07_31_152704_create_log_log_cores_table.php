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
        Schema::create('log_log_cores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->decimal('panjang', 10, 2);
            $table->date('tanggal');
            $table->string('tipe_transaksi'); // masuk / keluar
            $table->text('keterangan')->nullable();
            $table->string('referensi_type')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->decimal('qty', 15, 2);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai', 15, 2)->default(0);
            $table->decimal('stok_qty_before', 15, 2);
            $table->decimal('nilai_stok_before', 15, 2);
            $table->decimal('stok_qty_after', 15, 2);
            $table->decimal('nilai_stok_after', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_log_cores');
    }
};
