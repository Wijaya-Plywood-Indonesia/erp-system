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
        Schema::table('log_log_cores', function (Blueprint $table) {
            $table->foreignId('id_validator')->nullable()->after('keterangan')->constrained('users')->nullOnDelete();
            $table->timestamp('tanggal_validasi')->nullable()->after('id_validator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_log_cores', function (Blueprint $table) {
            $table->dropForeign(['id_validator']);
            $table->dropColumn(['id_validator', 'tanggal_validasi']);
        });
    }
};
