<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menyimpan akumulasi jumlah lembar yang sudah dikembalikan ke Gudang
     * Veneer Jadi dari palet ini (fitur "Kembalikan Sisa" di tab Serah
     * Terima Hotpress). Dipisah dari pemakaian (`bahan_hotpress.isi`) agar
     * laporan pemakaian riil tidak tercampur dengan retur, tapi tetap
     * ikut mengurangi `sisa` supaya tidak bisa dikembalikan dua kali.
     */
    public function up(): void
    {
        Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->integer('jumlah_dikembalikan')->default(0)->after('jumlah_lembar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->dropColumn('jumlah_dikembalikan');
        });
    }
};
