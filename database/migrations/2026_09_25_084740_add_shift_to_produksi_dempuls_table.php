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
        Schema::table('produksi_dempuls', function (Blueprint $table) {
            $table->string('shift')->nullable()->default('PAGI');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_dempuls', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
