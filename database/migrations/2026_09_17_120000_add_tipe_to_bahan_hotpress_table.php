<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom `tipe` untuk membedakan baris "pemakaian" (bahan
     * diambil dari gudang untuk hotpress — perilaku lama) dan "pengembalian"
     * (sisa veneer jadi yang tidak terpakai, dikembalikan ke gudang).
     *
     * Default 'pemakaian' otomatis mengisi seluruh baris lama (MySQL mengisi
     * default value ke baris existing saat ADD COLUMN NOT NULL DEFAULT ...),
     * jadi tidak ada baris lama yang ke-NULL dan lolos dari perhitungan sisa.
     */
    public function up(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->enum('tipe', ['pemakaian', 'pengembalian'])
                ->default('pemakaian')
                ->after('sumber');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
