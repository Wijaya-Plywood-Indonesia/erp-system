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
        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->integer('harga')->nullable()->after('kuantitas')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->dropColumn('harga');
        });
    }
};
