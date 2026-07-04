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
        Schema::table('gudang_veneer_jadis', function (Blueprint $table) {
            $table->timestamp('diterima_at')->nullable()->after('id_last_log');
            $table->foreignId('diterima_by')
                ->nullable()
                ->after('diterima_at')
                ->constrained('users')
                ->onDelete('set null');
            $table->string('status_gudang')->after('diterima_by')->default('belum diterima');
            $table->string('keterangan')->nullable()->after('status_gudang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gudang_veneer_jadis', function (Blueprint $table) {
            $table->dropForeign(['diterima_by']);
            $table->dropColumn(['diterima_at', 'diterima_by', 'status_gudang', 'keterangan_produksi']);
        });
    }
};
