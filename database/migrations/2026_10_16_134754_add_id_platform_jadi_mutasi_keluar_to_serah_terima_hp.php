<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->unsignedBigInteger('id_platform_jadi_mutasi_keluar')
                ->nullable()
                ->after('id_platform_mth_mutasi_keluar');

            $table->index('id_platform_jadi_mutasi_keluar', 'sth_id_platform_jadi_mutasi_keluar_idx');
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropIndex('sth_id_platform_jadi_mutasi_keluar_idx');
            $table->dropColumn('id_platform_jadi_mutasi_keluar');
        });
    }
};
