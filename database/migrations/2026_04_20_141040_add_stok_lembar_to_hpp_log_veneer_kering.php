<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hpp_log_veneer_kering', function (Blueprint $table) {
        $table->integer('total_lembar_masuk')->default(0)->after('kw');
        $table->integer('total_lembar_keluar')->default(0)->after('total_lembar_masuk');
        $table->integer('stok_akhir_lembar')->default(0)->after('total_lembar_keluar');
        $table->integer('stok_awal_lembar')->default(0)->after('stok_akhir_lembar');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hpp_log_veneer_kering', function (Blueprint $table) {
        $table->dropColumn([
            'total_lembar_masuk',
            'total_lembar_keluar', 
            'stok_akhir_lembar',
            'stok_awal_lembar',
        ]);
    });
    }
};
