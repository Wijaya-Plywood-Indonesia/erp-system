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
        Schema::table('pengaturan_absensis', function (Blueprint $table) {
            $table->integer('batas_hari_mundur_upload')->default(7)->after('auto_fix_batas_selisih_menit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengaturan_absensis', function (Blueprint $table) {
            $table->dropColumn('batas_hari_mundur_upload');
        });
    }
};
