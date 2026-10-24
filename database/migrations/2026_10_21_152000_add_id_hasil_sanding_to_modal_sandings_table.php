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
        Schema::table('modal_sandings', function (Blueprint $table) {
            $table->foreignId('id_hasil_sanding')
                ->nullable()
                ->after('id_serah_terima_hp')
                ->constrained('hasil_sandings')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modal_sandings', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_sanding']);
            $table->dropColumn('id_hasil_sanding');
        });
    }
};
