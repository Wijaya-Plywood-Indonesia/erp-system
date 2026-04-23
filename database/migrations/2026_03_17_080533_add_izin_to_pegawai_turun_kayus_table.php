<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai_turun_kayus', function (Blueprint $table) {
            $table->string('izin')->nullable()->after('jam_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai_turun_kayus', function (Blueprint $table) {
            $table->dropColumn('izin');
        });
    }
};