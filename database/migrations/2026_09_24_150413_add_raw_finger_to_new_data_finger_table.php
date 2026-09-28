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
        Schema::table('new_data_finger', function (Blueprint $table) {
            // Menyimpan semua tap mentah pada hari itu sebagai array JSON.
            // Format setiap item: { "waktu": "HH:MM:SS" }
            // Array sudah diurutkan dari yang paling awal.
            $table->json('raw_finger')->nullable()->after('jam_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_data_finger', function (Blueprint $table) {
            $table->dropColumn('raw_finger');
        });
    }
};
