<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enum tidak bisa diubah lewat Blueprint tanpa doctrine/dbal,
        // jadi pakai raw statement untuk menambah value 'gudang'.
        DB::statement("ALTER TABLE serah_terima_veneer_kering MODIFY tipe_sumber ENUM('dryer', 'kedi', 'gudang') NOT NULL");

        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            // Sumber dari mutasi keluar Gudang Veneer Kering (per palet).
            // Unique: satu palet cuma bisa masuk antrean serah-terima sekali,
            // konsisten dengan pola id_detail_hasil / id_detail_bongkar_kedi.
            $table->foreignId('id_mutasi_keluar_palet')
                ->nullable()
                ->after('id_detail_bongkar_kedi')
                ->constrained('veneer_kering_mutasi_keluar_palets')
                ->nullOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_mutasi_keluar_palet');
        });

        DB::statement("ALTER TABLE serah_terima_veneer_kering MODIFY tipe_sumber ENUM('dryer', 'kedi') NOT NULL");
    }
};