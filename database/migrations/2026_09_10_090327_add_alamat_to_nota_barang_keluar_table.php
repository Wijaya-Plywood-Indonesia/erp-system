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
        Schema::table('nota_barang_keluar', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('tujuan_nota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nota_barang_keluar', function (Blueprint $table) {
            $table->dropColumn('alamat');
        });
    }
};
