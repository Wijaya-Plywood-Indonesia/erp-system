<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('veneer_mutasi_details', function (Blueprint $table) {
            $table->enum('tipe_veneer', ['basah', 'kering', 'jadi'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veneer_mutasi_details', function (Blueprint $table) {
            $table->enum('tipe_veneer', ['basah', 'kering'])->change();
        });
    }
};
