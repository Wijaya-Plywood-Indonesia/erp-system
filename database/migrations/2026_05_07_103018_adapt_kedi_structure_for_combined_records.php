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
        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->date('tanggal_bongkar')->nullable()->after('tanggal');
        });

        Schema::table('validasi_kedi', function (Blueprint $table) {
            $table->enum('tipe', ['masuk', 'bongkar'])->default('masuk')->after('id_produksi_kedi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validasi_kedi', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });

        Schema::table('produksi_kedi', function (Blueprint $table) {
            $table->dropColumn('tanggal_bongkar');
        });
    }
};
