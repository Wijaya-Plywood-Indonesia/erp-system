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
        Schema::table('detail_nota_barang_keluar', function (Blueprint $table) {
            $table->decimal('harga', 15, 2)->nullable()->after('satuan');
            $table->string('custom_nama')->nullable()->after('nama_barang');
            $table->decimal('custom_m3', 10, 4)->nullable()->after('jumlah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_nota_barang_keluar', function (Blueprint $table) {
            $table->dropColumn(['harga', 'custom_nama', 'custom_m3']);
        });
    }
};
