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
        Schema::table('tempat_kayus', function (Blueprint $table) {
            $table->string('diserahkan_oleh')->nullable()->after('id_lahan');
            $table->string('diterima_oleh')->nullable()->after('diserahkan_oleh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tempat_kayus', function (Blueprint $table) {
            $table->dropColumn(['diserahkan_oleh', 'diterima_oleh']);
        });
    }
};
