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
        Schema::table('rencana_pegawais', function (Blueprint $table) {
            $table->dropColumn('nomor_meja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rencana_pegawais', function (Blueprint $table) {
            $table->integer('nomor_meja')->nullable();
        });
    }
};
