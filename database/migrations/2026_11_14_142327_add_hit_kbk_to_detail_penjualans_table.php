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
        Schema::table('detail_penjualans', function (Blueprint $table) {
            $table->string('hit_kbk', 10)->default('b')->after('custom_m3'); // 'b' (Banyak) atau 'm' (Kubikasi/M3)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_penjualans', function (Blueprint $table) {
            $table->dropColumn('hit_kbk');
        });
    }
};
