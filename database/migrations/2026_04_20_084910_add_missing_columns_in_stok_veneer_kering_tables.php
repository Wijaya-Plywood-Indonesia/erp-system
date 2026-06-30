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
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->foreignId('id_detail_hasil_dryer')
                ->nullable()
                ->after('id_produksi_dryer')
                ->constrained('detail_hasils')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->dropForeign(['id_detail_hasil_dryer']);
            $table->dropColumn('id_detail_hasil_dryer');
        });
    }
};
