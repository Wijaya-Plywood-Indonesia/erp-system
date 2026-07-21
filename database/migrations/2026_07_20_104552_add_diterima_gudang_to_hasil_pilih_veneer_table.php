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
        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            $table->timestamp('diterima_gudang_at')->nullable();
            $table->foreignId('diterima_gudang_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            // Drop foreign key & kolom saat rollback
            $table->dropForeign(['diterima_gudang_by']);
            $table->dropColumn(['diterima_gudang_at', 'diterima_gudang_by']);
        });
    }
};
