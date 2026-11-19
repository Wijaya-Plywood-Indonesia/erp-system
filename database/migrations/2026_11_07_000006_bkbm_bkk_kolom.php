<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bk', function (Blueprint $t) {
            $t->unsignedBigInteger('id_bmk_asal')->nullable()->index();   // BKR -> BMK lokal asalnya
            $t->string('ref_no_bkk')->nullable();                          // BKR -> nomor BKK di pabrik peminjam
        });
        Schema::table('bm', function (Blueprint $t) {
            $t->unsignedBigInteger('id_bkk_asal')->nullable()->index();   // BMR -> BKK lokal yang ditutup
        });
    }

    public function down(): void
    {
        Schema::table('bk', fn (Blueprint $t) => $t->dropColumn(['id_bmk_asal', 'ref_no_bkk']));
        Schema::table('bm', fn (Blueprint $t) => $t->dropColumn('id_bkk_asal'));
    }
};
