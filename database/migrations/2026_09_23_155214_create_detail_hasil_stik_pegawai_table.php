<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot untuk "nyusup" di Produksi Stik: satu baris Hasil Stik bisa
 * dikerjakan oleh lebih dari satu pegawai (mis. 2 pegawai mengerjakan
 * banyak ukuran barang sekaligus), dan satu pegawai bisa muncul di
 * banyak baris Hasil Stik (1 pegawai banyak barang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_hasil_stik_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_hasil_stik_id')
                ->constrained('detail_hasil_stik')
                ->cascadeOnDelete();
            $table->foreignId('detail_pegawai_stik_id')
                ->constrained('detail_pegawai_stik')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['detail_hasil_stik_id', 'detail_pegawai_stik_id'],
                'detail_hasil_stik_pegawai_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_hasil_stik_pegawai');
    }
};