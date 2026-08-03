<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_barang_detail', function (Blueprint $table) {
            // Keterangan per barang, misal: "untuk ganti bearing rusak di rotary"
            $table->text('keterangan')->nullable()->after('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_barang_details', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};