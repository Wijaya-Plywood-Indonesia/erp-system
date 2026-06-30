<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('referensi_harga_produksi', function (Blueprint $table) {
                $table->dropForeign(['id_ukuran']);
                $table->dropForeign(['id_jenis_kayu']);
            });
        }

        Schema::table('referensi_harga_produksi', function (Blueprint $table) {
            $table->foreignId('id_ukuran')->nullable()->change();
            $table->foreignId('id_jenis_kayu')->nullable()->change();
            $table->string('jenis_barang', 100)->nullable()->change();
            $table->string('kw', 50)->nullable()->change();
            $table->decimal('harga', 12, 4)->nullable()->change();
        });

        if (config('database.default') !== 'sqlite') {
            Schema::table('referensi_harga_produksi', function (Blueprint $table) {
                $table->foreign('id_ukuran')->references('id')->on('ukurans')->cascadeOnDelete();
                $table->foreign('id_jenis_kayu')->references('id')->on('jenis_kayus')->cascadeOnDelete();
            });
        }

        // Silakan jalankan seeder secara manual dari terminal jika diperlukan:
        // php artisan db:seed --class=ReferensiHargaProduksiSeeder
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('referensi_harga_produksi', function (Blueprint $table) {
                $table->dropForeign(['id_ukuran']);
                $table->dropForeign(['id_jenis_kayu']);
            });
        }

        Schema::table('referensi_harga_produksi', function (Blueprint $table) {
            $table->foreignId('id_ukuran')->nullable(false)->change();
            $table->foreignId('id_jenis_kayu')->nullable(false)->change();
            $table->string('jenis_barang', 100)->nullable(false)->change();
            $table->string('kw', 50)->nullable(false)->change();
            $table->decimal('harga', 12, 4)->nullable(false)->change();
        });

        if (config('database.default') !== 'sqlite') {
            Schema::table('referensi_harga_produksi', function (Blueprint $table) {
                $table->foreign('id_ukuran')->references('id')->on('ukurans')->cascadeOnDelete();
                $table->foreign('id_jenis_kayu')->references('id')->on('jenis_kayus')->cascadeOnDelete();
            });
        }
    }
};
