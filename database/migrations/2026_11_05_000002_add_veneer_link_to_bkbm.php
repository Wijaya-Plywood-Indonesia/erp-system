<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tautan mutasi veneer ke nota BK/BM baru (kolom lama id_nota_bk/id_nota_bm tidak disentuh).
        Schema::table('veneer_mutasis', function (Blueprint $t) {
            if (! Schema::hasColumn('veneer_mutasis', 'id_bk')) {
                $t->unsignedBigInteger('id_bk')->nullable()->index();
                $t->unsignedBigInteger('id_bm')->nullable()->index();
            }
        });
        foreach (['bk_detail', 'bm_detail'] as $tabel) {
            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                if (! Schema::hasColumn($tabel, 'ref_type')) {
                    $t->string('ref_type', 30)->nullable();      // mis. 'veneer'
                    $t->unsignedBigInteger('ref_id')->nullable(); // id baris di tabel sumber
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('veneer_mutasis', fn (Blueprint $t) => $t->dropColumn(['id_bk', 'id_bm']));
        foreach (['bk_detail', 'bm_detail'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn(['ref_type', 'ref_id']));
        }
    }
};
