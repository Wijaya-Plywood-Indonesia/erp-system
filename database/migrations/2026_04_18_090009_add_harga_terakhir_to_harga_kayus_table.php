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
            // Menambahkan kolom harga_terakhir setelah kolom harga_beli
            $table->bigInteger('harga_terakhir')->nullable()->after('harga_beli');
        });
    }

    public function down(): void
    {
        Schema::table('harga_kayus', function (Blueprint $table) {
            $table->dropColumn('harga_terakhir');
        });
    }
};
