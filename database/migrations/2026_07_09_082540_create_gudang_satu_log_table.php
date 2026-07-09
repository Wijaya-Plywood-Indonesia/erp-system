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
        Schema::create('gudang_satu_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            $table->decimal('panjang', 8, 2); // cm, misal 244
            $table->decimal('lebar',   8, 2); // cm, misal 122
            $table->decimal('tebal',   6, 2); // mm, misal 0.5 atau 3.7
            $table->string('kw_grade')->nullable();

            // Waktu & tipe
            $table->date('tanggal');
            $table->enum('tipe_transaksi', ['masuk', 'keluar']);
            $table->string('keterangan')->nullable();
            $table->nullableMorphs('referensi'); // ke ProduksiRotary, dll

            // Qty
            $table->integer('total_lembar')->nullable();
            $table->decimal('total_kubikasi', 15, 6)->nullable(); // m³

            // Komponen HPP per m³ (breakdown untuk audit)
            $table->decimal('hpp_pekerja',         20, 2)->nullable(); // ongkos pekerja / kubikasi
            $table->decimal('hpp_bahan_penolong',  20, 2)->nullable(); // total bahan penolong / kubikasi
            $table->decimal('hpp_average',         20, 2)->default(0); // total = pekerja+bahan

            $table->decimal('nilai_stok', 20, 2)->nullable(); // total_kubikasi × hpp_average

            // Snapshot SEBELUM transaksi
            $table->integer('stok_lembar_before')->default(0)->nullable();
            $table->decimal('stok_kubikasi_before', 15, 6)->default(0)->nullable();
            $table->decimal('nilai_stok_before',    20, 2)->default(0)->nullable();

            // Snapshot SESUDAH transaksi
            $table->integer('stok_lembar_after')->default(0)->nullable();
            $table->decimal('stok_kubikasi_after', 15, 6)->default(0)->nullable();
            $table->decimal('nilai_stok_after',    20, 2)->default(0)->nullable();

            $table->timestamps();

            $table->index(
                ['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'tanggal', 'id'],
                'idx_gudang_satu_log_kombinasi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gudang_satu_log');
    }
};
