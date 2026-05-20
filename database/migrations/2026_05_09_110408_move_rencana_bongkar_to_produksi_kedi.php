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
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->date('rencana_bongkar')->nullable()->after('tanggal');
        });

        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->dropColumn('rencana_bongkar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->date('rencana_bongkar')->nullable();
        });

        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->dropColumn('rencana_bongkar');
        });
    }
};
