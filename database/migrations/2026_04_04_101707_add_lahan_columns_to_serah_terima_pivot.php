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
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            // Untuk serah terima lahan_rotary
            $table->foreignId('id_lahan')
                ->nullable()
                ->after('id_detail_hasil_palet_rotary')
                ->constrained('lahans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_produksi')
                ->nullable()
                ->after('id_lahan')
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('jumlah_batang')->nullable()->after('id_produksi');
            $table->decimal('kubikasi', 15, 4)->nullable()->after('jumlah_batang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            $table->dropForeign(['id_lahan']);
            $table->dropForeign(['id_produksi']);
            $table->dropColumn(['id_lahan', 'id_produksi', 'jumlah_batang', 'kubikasi']);
        });
    }
};
