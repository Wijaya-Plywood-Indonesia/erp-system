<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modal Press Dryer (detail_masuks)
        Schema::table('detail_masuks', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_veneer_basah')
                ->nullable()
                ->after('id_produksi_dryer')
                ->constrained('serah_terima_veneer_basah')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // Modal Kedi (detail_masuk_kedi)
        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_veneer_basah')
                ->nullable()
                ->after('id_produksi_kedi')
                ->constrained('serah_terima_veneer_basah')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detail_masuks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_serah_terima_veneer_basah');
        });

        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_serah_terima_veneer_basah');
        });
    }
};
