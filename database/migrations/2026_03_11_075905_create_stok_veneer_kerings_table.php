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
        Schema::create('stok_veneer_kerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_dryer')
                ->nullable()
                ->constrained('produksi_press_dryers')
                ->nullOnDelete();

            // Identitas produk
            $table->foreignId('id_ukuran')->constrained('ukurans');
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->string('kw', 10)->comment('Kualitas: A, B, C, dst');

            // Info transaksi
            $table->enum('jenis_transaksi', ['masuk', 'keluar', 'koreksi'])->default('masuk');
            $table->date('tanggal_transaksi');
            $table->decimal('qty', 12, 4)->default(0)->comment('Jumlah lembar');
            $table->decimal('m3', 12, 6)->comment('Volume dalam m3');

            // Komponen HPP per m3
            $table->decimal('hpp_veneer_basah_per_m3', 15, 4)->default(1000000);
            $table->decimal('ongkos_dryer_per_m3', 15, 4)->default(0);
            $table->decimal('hpp_kering_per_m3', 15, 4)->default(0)
                ->comment('= hpp_veneer_basah + ongkos_dryer');

            // Nilai transaksi
            $table->decimal('nilai_transaksi', 20, 4)->default(0)
                ->comment('= hpp_kering_per_m3 × m3');

            // Snapshot stok sebelum & sesudah (moving average)
            $table->decimal('stok_m3_sebelum', 15, 6)->default(0);
            $table->decimal('nilai_stok_sebelum', 20, 4)->default(0);
            $table->decimal('stok_m3_sesudah', 15, 6)->default(0);
            $table->decimal('nilai_stok_sesudah', 20, 4)->default(0);
            $table->decimal('hpp_average', 15, 4)->default(0)
                ->comment('HPP rata-rata per m3 setelah transaksi ini');

            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Index performa untuk query stok per produk
            $table->index(
                ['id_ukuran', 'id_jenis_kayu', 'kw', 'tanggal_transaksi'],
                'idx_stok_produk_tanggal'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->dropForeign(['id_produksi_dryer']);
            $table->dropForeign(['id_ukuran']);
            $table->dropForeign(['id_jenis_kayu']);

            $table->dropIndex('idx_stok_produk_tanggal');
        });

};
