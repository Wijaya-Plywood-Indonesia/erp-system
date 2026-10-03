<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom ini dibutuhkan oleh BahanHotpress::sisaBisaDikembalikan()
        // dan fitur "Kembalikan ke Gudang" untuk membedakan baris
        // "pemakaian" (bahan diambil untuk produksi) dan "pengembalian"
        // (sisa bahan dikembalikan ke gudang).
        if (! Schema::hasColumn('bahan_hotpress', 'tipe')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->string('tipe')->default('pemakaian')->after('sumber');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bahan_hotpress', 'tipe')) {
            Schema::table('bahan_hotpress', function (Blueprint $table) {
                $table->dropColumn('tipe');
            });
        }
    }
};
