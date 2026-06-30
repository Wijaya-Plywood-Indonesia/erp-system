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
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            if (Schema::hasColumn('ganti_pisau_rotaries', 'jam_mulai_ganti_pisau')) {
                $table->time('jam_mulai_ganti_pisau')->nullable()->change();
            }

            if (Schema::hasColumn('ganti_pisau_rotaries', 'jam_selesai_ganti')) {
                $table->time('jam_selesai_ganti')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            if (Schema::hasColumn('ganti_pisau_rotaries', 'jam_mulai_ganti_pisau')) {
                $table->time('jam_mulai_ganti_pisau')->nullable(false)->change();
            }

            if (Schema::hasColumn('ganti_pisau_rotaries', 'jam_selesai_ganti')) {
                $table->time('jam_selesai_ganti')->nullable(false)->change();
            }
        });
    }
};
