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
        Schema::table('pegawai_turun_kayus', function (Blueprint $table) {
            $table->text('ket')->nullable()->after('jam_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai_turun_kayus', function (Blueprint $table) {
            $table->dropColumn('ket');
        });
    }
};
