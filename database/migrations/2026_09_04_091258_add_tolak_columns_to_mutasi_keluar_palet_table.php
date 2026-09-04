<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'veneer_jadi_mutasi_keluar_palets',
            'platform_jadi_mutasi_keluar_palets',
            'triplek_jadi_mutasi_keluar_palets',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('ditolak_by')->nullable()->after('diterima_at');
                $table->text('alasan_tolak')->nullable()->after('ditolak_by');
                $table->timestamp('ditolak_at')->nullable()->after('alasan_tolak');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'veneer_jadi_mutasi_keluar_palets',
            'platform_jadi_mutasi_keluar_palets',
            'triplek_jadi_mutasi_keluar_palets',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['ditolak_by', 'alasan_tolak', 'ditolak_at']);
            });
        }
    }
};