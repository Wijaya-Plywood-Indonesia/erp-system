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
        Schema::table('hasil_repairs', function (Blueprint $table) {
            $table->timestamp('diserahkan_at')->nullable()->after('keterangan');
            $table->foreignId('diserahkan_by')
                ->nullable()
                ->after('diserahkan_at')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hasil_repairs', function (Blueprint $table) {
            $table->dropForeign(['diserahkan_by']);
            $table->dropColumn(['diserahkan_at', 'diserahkan_by']);
        });
    }
};
