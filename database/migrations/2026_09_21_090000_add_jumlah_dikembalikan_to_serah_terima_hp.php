<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sama seperti jumlah_dikembalikan di *_jadi_mutasi_keluar_palets (fitur
 * pengembalian Hotpress) — kolom ini jadi "running counter" berapa lembar
 * dari satu baris serah_terima_hp yang sudah dikembalikan ke gudang.
 *
 * Ditaruh di serah_terima_hp (bukan di modal_sandings) karena satu baris
 * serah_terima_hp bisa dipakai oleh BEBERAPA baris modal_sandings (sisa
 * dipakai sedikit-sedikit), persis seperti satu palet veneer/platform/
 * triplek bisa dipakai banyak baris bahan_hotpress. Jadi "sisa yang belum
 * dikembalikan" itu properti si serah_terima_hp, bukan properti satu baris
 * modal_sandings tertentu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            if (! Schema::hasColumn('serah_terima_hp', 'jumlah_dikembalikan')) {
                $table->decimal('jumlah_dikembalikan', 15, 2)->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            if (Schema::hasColumn('serah_terima_hp', 'jumlah_dikembalikan')) {
                $table->dropColumn('jumlah_dikembalikan');
            }
        });
    }
};
