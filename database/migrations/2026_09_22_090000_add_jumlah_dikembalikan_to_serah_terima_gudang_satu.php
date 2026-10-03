<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sama seperti pola "Kembalikan ke Gudang" di Hotpress: jumlah_dikembalikan
     * disimpan di baris SUMBER (di sini: serah_terima_gudang_satu, berperan
     * sama seperti palet veneer/platform/triplek di Hotpress), BUKAN di baris
     * pemakaian (bahan_terima_gudang_satu). Alasannya sama persis: "sisa yang
     * belum dipakai" adalah properti sumbernya, bukan properti satu baris
     * pemakaian tertentu.
     *
     * Idempotent lewat Schema::hasColumn, aman dijalankan ulang.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('serah_terima_gudang_satu', 'jumlah_dikembalikan')) {
            Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
                $table->unsignedInteger('jumlah_dikembalikan')->default(0)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('serah_terima_gudang_satu', 'jumlah_dikembalikan')) {
            Schema::table('serah_terima_gudang_satu', function (Blueprint $table) {
                $table->dropColumn('jumlah_dikembalikan');
            });
        }
    }
};
