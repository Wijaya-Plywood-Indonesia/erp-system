<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hpp_average_logs', function (Blueprint $table) {
            // Selisih khusus "Tempat Kayu" saat opname (null = log lama, pakai after - before)
            $table->integer('tk_delta_batang')->nullable();
            $table->decimal('tk_delta_kubikasi', 12, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hpp_average_logs', function (Blueprint $table) {
            $table->dropColumn(['tk_delta_batang', 'tk_delta_kubikasi']);
        });
    }
};