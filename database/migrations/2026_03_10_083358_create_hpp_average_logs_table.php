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
        Schema::create('hpp_average_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            $table->string('grade', 5); // Casting to A,B,C
            $table->integer('panjang'); // cm

            // ── Waktu & tipe ──────────────────────────────────────────────────
            $table->date('tanggal');               // dari KayuMasuk.tgl_kayu_masuk
            $table->enum('tipe_transaksi', ['masuk', 'keluar']);
            $table->string('keterangan')->nullable(); // "Nota #N101 · KayuMasuk #5"
            $table->nullableMorphs('referensi');

            $table->integer('total_batang');
            $table->decimal('total_kubikasi', 15, 4); // hasil rumus (p×d²×qty×0.785)/1jt

            $table->decimal('harga', 20, 2);
            $table->decimal('nilai_stok', 20, 2); // qty_kubikasi × harga_satuan

            // ── Snapshot SEBELUM ──────────────────────────────────────────────
            $table->integer('stok_batang_before')->default(0)->nullable();
            $table->decimal('stok_kubikasi_before', 15, 4)->default(0)->nullable();
            $table->decimal('nilai_stok_before',    20, 2)->default(0)->nullable();

            // ── Snapshot SESUDAH ──────────────────────────────────────────────
            $table->integer('stok_batang_after')->default(0)->nullable();
            $table->decimal('stok_kubikasi_after', 16, 6)->default(0)->nullable();
            $table->decimal('nilai_stok_after',    20, 4)->default(0)->nullable();

            $table->decimal('hpp_average', 20, 2)->default(0);

            $table->timestamps();

            // Index untuk query per kombinasi + kronologi
            $table->index(
                ['id_jenis_kayu', 'grade', 'panjang', 'tanggal', 'id'],
                'idx_hpp_log_kombinasi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hpp_average_logs');
    }
};
