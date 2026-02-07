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
        //
        Schema::create('jurnal_umum', function (Blueprint $table) {
            $table->id();

            $table->date('tgl')->nullable();

            $table->integer('jurnal')->nullable();

            // No Akun biasanya VARCHAR karena ada titik, contoh 1411.01
            $table->string('no_akun');

            // No transaksi
            $table->string('no-dokumen')->nullable();

            // mm → mungkin kode debit/kredit lain?
            $table->integer('mm')->nullable();

            $table->string('nama')->nullable();
            $table->string('keterangan')->nullable();

            // map (d/k)
            $table->string('map', 5)->nullable();

            // hit_kbk → bisa boolean atau string
            $table->string('hit_kbk', 10)->nullable();

            $table->integer('banyak')->nullable();

            // m3 → decimal presisi tinggi
            $table->decimal('m3', 10, 4)->nullable();

            // harga → gunakan decimal
            $table->integer('harga')->nullable();

            // tambahan tracking
            $table->string('created_by')->nullable();
            $table->string('status')->default('belum sinkron');
            $table->dateTime('synced_at')->nullable();
            $table->string('synced_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('jurnal_umum');
    }
};
