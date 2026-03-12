<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: hpp_veneer_basah_bahan_penolong
 *
 * Breakdown bahan penolong per log transaksi masuk veneer basah.
 * Harga diambil dari bahan_penolong_produksi.harga via FK bahan_penolong_id.
 *
 * hpp_per_m3 = nilai_total / total_kubikasi_veneer_hari_itu
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_veneer_basah_bahan_penolong', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_log')
                ->constrained('hpp_veneer_basah_logs')
                ->cascadeOnDelete();

            $table->foreignId('bahan_penolong_id')
                ->constrained('bahan_penolong_produksi')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Snapshot nilai saat transaksi — supaya history tidak berubah jika harga master diubah
            $table->string('nama_bahan');           // snapshot nama_bahan_penolong
            $table->string('satuan')->nullable();   // snapshot satuan
            $table->decimal('jumlah',       10, 2); // qty yang dipakai
            $table->decimal('harga_satuan', 20, 2); // snapshot dari bahan_penolong_produksi.harga
            $table->decimal('nilai_total',  20, 2); // jumlah × harga_satuan
            $table->decimal('hpp_per_m3',   20, 4); // nilai_total / kubikasi veneer hari itu

            $table->timestamps();

            $table->index('id_log', 'idx_hpp_vb_bahan_log');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_veneer_basah_bahan_penolong');
    }
};