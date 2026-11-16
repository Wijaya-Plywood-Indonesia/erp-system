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
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('no_nota')->unique();
            $table->string('tujuan_nota');
            $table->string('alamat')->nullable();
            $table->string('metode_pembayaran', 50)->nullable();
            $table->foreignId('id_rekening_perusahaan')
                ->nullable()
                ->constrained('rekening_perusahaan')
                ->nullOnDelete();

            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('ppn_persen', 5, 2)->default(0);
            $table->decimal('ppn_nominal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->string('kendaraan')->nullable();
            $table->string('plat_kendaraan')->nullable();
            $table->string('nama_sopir')->nullable();

            $table->string('status_sync_akuntansi')->default('draft'); // draft, synced, failed
            $table->text('sync_response')->nullable();

            $table->foreignId('dibuat_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('divalidasi_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('detail_penjualans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')
                ->constrained('penjualans')
                ->cascadeOnDelete();

            $table->string('nama_barang');
            $table->string('custom_nama')->nullable();
            $table->decimal('jumlah', 15, 2)->default(1);
            $table->decimal('custom_m3', 10, 4)->nullable();
            $table->string('satuan', 50)->default('pcs');
            $table->decimal('harga', 15, 2)->nullable();
            $table->decimal('potongan', 15, 2)->nullable()->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_penjualans');
        Schema::dropIfExists('penjualans');
    }
};
