<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->unsignedBigInteger('id_produksi_joint')->nullable()->after('id_produksi_repair');
            $table->index('id_produksi_joint');
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropColumn('id_produksi_joint');
        });
    }
};
