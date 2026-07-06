<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            // Sumber alternatif selain triplek: hasil platform dari hotpress
            $table->foreignId('id_platform_hasil_hp')
                ->nullable()
                ->after('id_triplek_hasil_hp')
                ->constrained('platform_hasil_hp')
                ->nullOnDelete();

            // Tujuan alternatif selain graji triplek: produksi sanding
            $table->foreignId('id_produksi_sanding')
                ->nullable()
                ->after('id_produksi_graji_triplek')
                ->constrained('produksi_sandings')
                ->nullOnDelete();

            $table->foreignId('id_triplek_hasil_hp')
                ->nullable()
                ->change();

        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_platform_hasil_hp');
            $table->dropConstrainedForeignId('id_produksi_sanding');
            $table->foreignId('id_triplek_hasil_hp')
                ->nullable(false)
                ->change();
        });
    }
};
