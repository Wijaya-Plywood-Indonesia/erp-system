<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memperluas pola pengembalian yang SUDAH BENAR dari Veneer (lihat
     * migrasi 2026_09_17_180000) ke Platform dan Triplek:
     * `jumlah_dikembalikan` disimpan di tabel PALET masing-masing, bukan
     * di `bahan_hotpress`, dan bukan lewat kolom `tipe` (baris baru per
     * pengembalian).
     *
     * Rumus sisa untuk ketiganya konsisten:
     *   sisa = jumlah_lembar - SUM(isi dari bahan_hotpress) - jumlah_dikembalikan
     *
     * Migrasi ini idempotent (aman dijalankan berkali-kali / dari kondisi
     * manapun) dan juga membersihkan sisa kolom `tipe` /
     * `jumlah_dikembalikan` di `bahan_hotpress` kalau pernah dicoba lewat
     * pendekatan lama (baris pengembalian sebagai row baru).
     */
    public function up(): void
    {
        if (Schema::hasColumn('bahan_hotpress', 'tipe')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->dropColumn('tipe');
            });
        }

        if (Schema::hasColumn('bahan_hotpress', 'jumlah_dikembalikan')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }

        if (! Schema::hasColumn('veneer_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('jumlah_lembar');
            });
        }

        if (! Schema::hasColumn('platform_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('platform_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('jumlah_lembar');
            });
        }

        if (! Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('triplek_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('jumlah_lembar');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('platform_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('platform_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }

        if (Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('triplek_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }
    }
};
