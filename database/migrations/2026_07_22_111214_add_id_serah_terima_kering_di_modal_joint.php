<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modal_joint', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_veneer_kering')
                ->nullable()
                ->after('id_produksi_joint')
                ->constrained('serah_terima_veneer_kering')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modal_joint', function (Blueprint $table) {
            $table->dropForeign(['id_serah_terima_veneer_kering']);
            $table->dropColumn('id_serah_terima_veneer_kering');
        });
    }
};
