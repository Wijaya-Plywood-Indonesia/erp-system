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
        Schema::table('modal_pilih_veneer', function (Blueprint $table) {
            $table->foreignId('id_stok_veneer_jadi')
                ->nullable()
                ->after('id_produksi_pilih_veneer')
                ->constrained('stok_veneer_jadi')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modal_pilih_veneer', function (Blueprint $table) {
            $table->dropForeign(['id_stok_veneer_jadi']);
            $table->dropColumn('id_stok_veneer_jadi');
        });
    }
};
