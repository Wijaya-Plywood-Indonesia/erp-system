<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang_veneer_kering', function (Blueprint $table) {
            // Siapa yang menerima barang (user login saat menekan tombol Terima).
            $table->foreignId('diterima_oleh')
                ->nullable()
                ->after('keterangan')
                ->constrained('users')
                ->nullOnDelete();

            // Jejak ke baris VeneerMutasiDetail asal, biar bisa ditelusuri
            // dan mencegah double-receive.
            $table->foreignId('id_veneer_mutasi_detail')
                ->nullable()
                ->after('diterima_oleh')
                ->constrained('veneer_mutasi_details')
                ->nullOnDelete();

            // NOTE: kolom `keterangan` sudah ada di tabel ini (diisi dari VM).
        });
    }

    public function down(): void
    {
        Schema::table('gudang_veneer_kering', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_veneer_mutasi_detail');
            $table->dropConstrainedForeignId('diterima_oleh');
        });
    }
};