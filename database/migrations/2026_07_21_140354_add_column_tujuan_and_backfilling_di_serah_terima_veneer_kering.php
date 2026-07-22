<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom tujuan (nullable)
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->string('tujuan')->nullable()->after('jenis_terima');
        });

        // 2. Backfill data lama yang memenuhi kriteria:
        //    - diterima_oleh != '-'
        //    - tipe_sumber IN ('gudang', 'gudang_jadi')
        DB::table('serah_terima_veneer_kering')
            ->where('diterima_oleh', '!=', '-')
            ->whereIn('tipe_sumber', ['gudang', 'gudang_jadi'])
            ->update(['tujuan' => 'repair']);
    }

    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropColumn('tujuan');
        });
    }
};
