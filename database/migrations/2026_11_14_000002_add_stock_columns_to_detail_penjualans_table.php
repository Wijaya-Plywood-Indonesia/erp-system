<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detail_penjualans', function (Blueprint $table) {
            $table->string('kategori_barang', 50)->nullable()->after('penjualan_id');
            $table->foreignId('id_ukuran')->nullable()->after('kategori_barang')->constrained('ukurans')->nullOnDelete();
            $table->foreignId('id_jenis_kayu')->nullable()->after('id_ukuran')->constrained('jenis_kayus')->nullOnDelete();
            $table->string('kw_grade', 50)->nullable()->after('id_jenis_kayu');
            $table->string('ukuran_key', 100)->nullable()->after('kw_grade');
            $table->string('tipe_veneer', 50)->nullable()->after('ukuran_key');
            $table->foreignId('id_barang_umum')->nullable()->after('tipe_veneer')->constrained('barang_umum')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detail_penjualans', function (Blueprint $table) {
            $table->dropForeign(['id_ukuran']);
            $table->dropForeign(['id_jenis_kayu']);
            $table->dropForeign(['id_barang_umum']);
            $table->dropColumn([
                'kategori_barang',
                'id_ukuran',
                'id_jenis_kayu',
                'kw_grade',
                'ukuran_key',
                'tipe_veneer',
                'id_barang_umum',
            ]);
        });
    }
};
