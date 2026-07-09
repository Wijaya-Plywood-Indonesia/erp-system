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
        Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->foreignId('diterima_by')->nullable()->after('jumlah_lembar')->constrained('users');
            $table->timestamp('diterima_at')->nullable()->after('diterima_by');
        });

        Schema::table('platform_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->foreignId('diterima_by')->nullable()->after('jumlah_lembar')->constrained('users');
            $table->timestamp('diterima_at')->nullable()->after('diterima_by');
        });
    }

    public function down(): void
    {
        Schema::table('veneer_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diterima_by');
            $table->dropColumn('diterima_at');
        });

        Schema::table('platform_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diterima_by');
            $table->dropColumn('diterima_at');
        });
    }
};
