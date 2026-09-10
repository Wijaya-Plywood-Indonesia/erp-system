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
            $table->foreignId('id_pegawai')
                ->nullable()
                ->after('id_absensi')
                ->constrained('pegawais')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_absensis', function (Blueprint $table) {
            $table->dropForeign(['id_pegawai']);
            $table->dropColumn('id_pegawai');
        });
    }
};
