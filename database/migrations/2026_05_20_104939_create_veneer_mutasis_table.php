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
        Schema::create('veneer_mutasis', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->enum('tipe_transaksi', ['masuk', 'keluar']);
            $table->enum('tipe_veneer', ['basah', 'kering']);
            $table->foreignId('id_ukuran')->constrained('ukurans')->cascadeOnDelete();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();
            $table->string('kw');
            $table->integer('qty');
            $table->decimal('m3', 10, 6);
            $table->string('no_nota')->nullable();
            $table->string('tujuan_nota')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('id_nota_bk')->nullable()->constrained('nota_barang_keluar')->nullOnDelete();
            $table->foreignId('id_nota_bm')->nullable()->constrained('nota_barang_masuks')->nullOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veneer_mutasis');
    }
};
