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
        Schema::table('modal_repairs', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('nomor_palet');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modal_repairs', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};