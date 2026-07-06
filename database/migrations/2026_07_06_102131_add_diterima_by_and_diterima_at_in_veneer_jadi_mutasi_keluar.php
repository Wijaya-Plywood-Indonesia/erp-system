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
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->foreignId('diterima_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamp('diterima_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->dropForeign(['diterima_by']);
            $table->dropColumn(['diterima_by', 'diterima_at']);
        });
    }
};
