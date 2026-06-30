<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referensi_harga_produksi', function (Blueprint $table) {
            $table->foreignId('id_sub_anak_akun')
                ->nullable()
                ->after('id_jenis_kayu')
                ->constrained('sub_anak_akuns')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referensi_harga_produksi', function (Blueprint $table) {
            $table->dropForeign(['id_sub_anak_akun']);
            $table->dropColumn('id_sub_anak_akun');
        });
    }
};
