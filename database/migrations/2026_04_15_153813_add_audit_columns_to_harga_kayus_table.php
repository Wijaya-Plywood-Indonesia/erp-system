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
        Schema::table('harga_kayus', function (Blueprint $table) {
            $table->string('updated_by')->nullable();

            // Mencatat siapa yang menyetujui (Checker)
            $table->string('approved_by')->nullable();

            // Harga kayu baru
            $table->integer('harga_baru')->nullable()->after('harga_beli');

            // Status alur kerja
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harga_kayus', function (Blueprint $table) {
            $table->dropColumn('harga_baru');
            $table->dropColumn(['updated_by', 'approved_by', 'status']);
        });
    }
};
