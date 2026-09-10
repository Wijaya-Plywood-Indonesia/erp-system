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
        Schema::table('detail_absensis', function (Blueprint $table) {
            $table->string('nama_pegawai')->nullable()->after('kode_pegawai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_absensis', function (Blueprint $table) {
            $table->dropColumn('nama_pegawai');
        });
    }
};
