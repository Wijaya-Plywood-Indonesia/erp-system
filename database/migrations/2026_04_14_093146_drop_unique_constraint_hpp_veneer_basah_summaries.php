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
    Schema::table('hpp_veneer_basah_summaries', function (Blueprint $table) {
        $table->dropUnique('uq_hpp_vb_summary_kombinasi');
    });
}

public function down(): void
{
    Schema::table('hpp_veneer_basah_summaries', function (Blueprint $table) {
        $table->unique(['id_jenis_kayu', 'panjang', 'lebar', 'tebal'], 'uq_hpp_vb_summary_kombinasi');
    });
}
};
