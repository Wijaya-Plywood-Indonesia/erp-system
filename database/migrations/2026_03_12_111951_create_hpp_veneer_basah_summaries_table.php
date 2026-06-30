<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel: hpp_veneer_basah_summaries
 * Stok terkini veneer basah per kombinasi jenis_kayu + ukuran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_veneer_basah_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            // Ukuran veneer — kombinasi ini unik
            $table->decimal('panjang', 8, 2); // cm
            $table->decimal('lebar',   8, 2); // cm
            $table->decimal('tebal',   6, 2); // mm

            // Stok saat ini
            $table->integer('stok_lembar')->default(0)->nullable();
            $table->decimal('stok_kubikasi', 15, 6)->default(0)->nullable(); // m³
            $table->decimal('nilai_stok',    20, 2)->default(0)->nullable(); // Rp

            // HPP average per m³ terkini (moving average, update tiap masuk)
            $table->decimal('hpp_average',        20, 2)->default(0)->nullable();

            // Komponen HPP dari transaksi masuk terakhir (untuk referensi)
            $table->decimal('hpp_kayu_last',          20, 2)->nullable();
            $table->decimal('hpp_pekerja_last',        20, 2)->nullable();
            $table->decimal('hpp_mesin_last',          20, 2)->nullable();
            $table->decimal('hpp_bahan_penolong_last', 20, 2)->nullable();

            // Referensi ke log terakhir
            $table->foreignId('id_last_log')
                ->nullable()
                ->constrained('hpp_veneer_basah_logs')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['id_jenis_kayu', 'panjang', 'lebar', 'tebal'],
                'uq_hpp_vb_summary_kombinasi'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_veneer_basah_summaries');
    }
};