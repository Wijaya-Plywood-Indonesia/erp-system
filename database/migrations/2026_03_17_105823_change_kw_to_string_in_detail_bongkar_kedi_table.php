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
        Schema::table('detail_bongkar_kedi', function (Blueprint $table) {
            if (Schema::hasColumn('detail_bongkar_kedi', 'kw')) {
                $table->string('kw', 20)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_bongkar_kedi', function (Blueprint $table) {
            if (Schema::hasColumn('detail_bongkar_kedi', 'kw')) {
                $table->integer('kw')->change();
            }
        });
    }
};
