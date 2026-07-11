<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti identitas produk platform jadi: id_jenis_kayu -> id_jenis_barang.
 *
 * Catatan MySQL: FK menumpang pada index, jadi urutan wajib
 * drop FOREIGN KEY dulu -> baru drop INDEX -> baru drop kolom.
 * Semua langkah diberi guard agar aman dijalankan ulang bila
 * migrate sebelumnya gagal di tengah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── stok_platform_jadi ──
        if (Schema::hasColumn('stok_platform_jadi', 'id_jenis_kayu')) {
            Schema::table('stok_platform_jadi', function (Blueprint $table) {
                $table->dropConstrainedForeignId('id_jenis_kayu');
            });
        }

        if (! Schema::hasColumn('stok_platform_jadi', 'id_jenis_barang')) {
            Schema::table('stok_platform_jadi', function (Blueprint $table) {
                $table->foreignId('id_jenis_barang')
                    ->after('id')
                    ->constrained('jenis_barang')
                    ->cascadeOnDelete();
            });
        }

        // ── hpp_platform_jadi_log ──
        if (Schema::hasColumn('hpp_platform_jadi_log', 'id_jenis_kayu')) {
            // 1. Lepas FK dulu (dia menumpang di index kombinasi)
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropForeign(['id_jenis_kayu']);
            });

            // 2. Baru index bisa di-drop
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropIndex('idx_hpp_platform_jadi_log_kombinasi');
            });

            // 3. Terakhir kolomnya
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropColumn('id_jenis_kayu');
            });
        }

        if (! Schema::hasColumn('hpp_platform_jadi_log', 'id_jenis_barang')) {
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->foreignId('id_jenis_barang')
                    ->after('id')
                    ->constrained('jenis_barang')
                    ->cascadeOnDelete();
                $table->index(
                    ['id_jenis_barang', 'panjang', 'lebar', 'tebal', 'kw_grade', 'tanggal', 'id'],
                    'idx_hpp_platform_jadi_log_kombinasi'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hpp_platform_jadi_log', 'id_jenis_barang')) {
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropForeign(['id_jenis_barang']);
            });
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropIndex('idx_hpp_platform_jadi_log_kombinasi');
            });
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->dropColumn('id_jenis_barang');
            });
        }

        if (! Schema::hasColumn('hpp_platform_jadi_log', 'id_jenis_kayu')) {
            Schema::table('hpp_platform_jadi_log', function (Blueprint $table) {
                $table->foreignId('id_jenis_kayu')->after('id')->constrained('jenis_kayus')->cascadeOnDelete();
                $table->index(
                    ['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade', 'tanggal', 'id'],
                    'idx_hpp_platform_jadi_log_kombinasi'
                );
            });
        }

        if (Schema::hasColumn('stok_platform_jadi', 'id_jenis_barang')) {
            Schema::table('stok_platform_jadi', function (Blueprint $table) {
                $table->dropConstrainedForeignId('id_jenis_barang');
            });
        }

        if (! Schema::hasColumn('stok_platform_jadi', 'id_jenis_kayu')) {
            Schema::table('stok_platform_jadi', function (Blueprint $table) {
                $table->foreignId('id_jenis_kayu')->after('id')->constrained('jenis_kayus')->cascadeOnDelete();
            });
        }
    }
};