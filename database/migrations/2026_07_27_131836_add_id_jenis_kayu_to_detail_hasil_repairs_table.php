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
        Schema::table('detail_hasil_repairs', function (Blueprint $table) {
            $table->foreignId('id_jenis_kayu')
                ->nullable()
                ->after('id_modal_repair')
                ->constrained('jenis_kayus')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_hasil_repairs', function (Blueprint $table) {
            $table->dropForeign(['id_jenis_kayu']);
            $table->dropColumn('id_jenis_kayu');
        });
    }
};
