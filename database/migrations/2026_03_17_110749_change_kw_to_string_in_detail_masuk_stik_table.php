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
        Schema::table('detail_masuk_stik', function (Blueprint $table) {
            if (Schema::hasColumn('detail_masuk_stik', 'kw')) {
                $table->string('kw')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_masuk_stik', function (Blueprint $table) {
            if (Schema::hasColumn('detail_masuk_stik', 'kw')) {
                $table->string('kw')->nullable()->change();
            }
        });
    }
};
