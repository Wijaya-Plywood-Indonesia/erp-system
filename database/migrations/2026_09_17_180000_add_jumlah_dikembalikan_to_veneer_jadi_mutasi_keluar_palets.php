<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Percobaan ke-3 (semoga yang benar 🙏).
     *
     * Pelajaran dari 2 percobaan sebelumnya:
     *  - v1: kolom `tipe` di bahan_hotpress, tiap pengembalian = baris baru.
     *        Ditolak karena bikin tabel penuh baris membingungkan.
     *  - v2: kolom `jumlah_dikembalikan` di bahan_hotpress (per baris
     *        pemakaian). Salah basis: sisa dihitung dari `isi -
     *        jumlah_dikembalikan` milik baris itu sendiri (mis. 90 - 0 =
     *        90), padahal yang benar adalah sisa fisik di PALET yang belum
     *        pernah "disentuh" oleh baris pemakaian manapun.
     *
     * v3 (final): jumlah_dikembalikan pindah ke tabel PALET
     * (veneer_jadi_mutasi_keluar_palets), karena "sisa yang belum
     * dipakai" itu memang properti palet, bukan properti 1 baris
     * pemakaian. Contoh: palet 100 lembar, 1 baris pemakaian isi=90 →
     * sisa yang bisa dikembalikan = 100 - 90 - jumlah_dikembalikan.
     *
     * Migrasi ini aman dijalankan dari kondisi manapun (idempotent lewat
     * Schema::hasColumn), termasuk kalau v1 atau v2 sempat dijalankan.
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('veneer_jadi_mutasi_keluar_palets', 'jumlah_dikembalikan')) {
            Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }
    }
};
