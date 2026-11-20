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
        Schema::table('hpp_triplek_jadi_log', function (Blueprint $table) {
            // Default sort + filter rentang tanggal
            $table->index(['tanggal', 'id'], 'idx_hpp_triplek_jadi_log_tanggal_id');
            // Filter jenis kayu + sort tanggal
            $table->index(['id_jenis_kayu', 'tanggal', 'id'], 'idx_hpp_triplek_jadi_log_kayu_tanggal');
            // Filter ukuran tanpa jenis kayu + query DISTINCT dropdown
            $table->index(['panjang', 'lebar', 'tebal'], 'idx_hpp_triplek_jadi_log_ukuran');
            $table->index('tebal', 'idx_hpp_triplek_jadi_log_tebal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hpp_triplek_jadi_log', function (Blueprint $table) {
            $table->dropIndex('idx_hpp_triplek_jadi_log_tanggal_id');
            $table->dropIndex('idx_hpp_triplek_jadi_log_kayu_tanggal');
            $table->dropIndex('idx_hpp_triplek_jadi_log_ukuran');
            $table->dropIndex('idx_hpp_triplek_jadi_log_tebal');
        });
    }
};
