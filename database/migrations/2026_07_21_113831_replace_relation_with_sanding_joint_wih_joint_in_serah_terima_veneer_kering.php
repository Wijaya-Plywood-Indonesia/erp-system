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
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            // Hapus foreign key & kolom milik sanding joint
            $table->dropForeign(['id_hasil_sanding_joint']);
            $table->dropColumn('id_hasil_sanding_joint');

            // Tambah kolom baru untuk hasil joint
            $table->foreignId('id_hasil_joint')
                ->nullable()
                ->after('id_detail_bongkar_kedi')
                ->constrained('hasil_joint')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            // Hapus foreign key & kolom hasil joint
            $table->dropForeign(['id_hasil_joint']);
            $table->dropColumn('id_hasil_joint');

            // Kembalikan kolom milik sanding joint
            $table->foreignId('id_hasil_sanding_joint')
                ->nullable()
                ->after('id_detail_bongkar_kedi')
                ->constrained('hasil_sanding_joint')
                ->nullOnDelete();
        });
    }
};
