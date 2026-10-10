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
        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            $table->string('kw')->nullable()->change();
            $table->integer('no_palet')->nullable()->change();
            $table->integer('jumlah')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            $table->string('kw')->nullable(false)->change();
            $table->integer('no_palet')->nullable(false)->change();
            $table->integer('jumlah')->nullable(false)->change();
        });
    }
};
