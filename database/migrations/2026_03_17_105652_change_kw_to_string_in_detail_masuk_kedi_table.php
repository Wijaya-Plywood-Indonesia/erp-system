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
        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            if (Schema::hasColumn('detail_masuk_kedi', 'kw')) {
                // Gunakan change() untuk mengubah tipe data
                // Kita tambahkan nullable() dan length 20 sebagai pengaman
                $table->string('kw')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_masuk_kedi', function (Blueprint $table) {
            if (Schema::hasColumn('detail_masuk_kedi', 'kw')) {
                $table->integer('kw')->change();
            }
        });
    }
};
