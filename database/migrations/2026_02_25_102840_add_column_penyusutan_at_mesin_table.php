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
        Schema::table('mesins', function (Blueprint $table) {
            $table->decimal('penyusutan', 15, 2)->default(0)->after('ongkos_mesin');
        });
    }

    /**
     * Reverse the migrations.    */
    public function down(): void
    {
        Schema::table('mesins', function (Blueprint $table) {
            $table->dropColumn('penyusutan');
        });
    }
};