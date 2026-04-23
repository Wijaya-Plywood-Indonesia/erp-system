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
    Schema::table('stok_veneer_kerings', function (Blueprint $table) {
        $table->integer('stok_lembar_sebelum')->default(0)->after('qty');
        $table->integer('stok_lembar_sesudah')->default(0)->after('stok_lembar_sebelum');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    Schema::table('stok_veneer_kerings', function (Blueprint $table) {
        $table->dropColumn(['stok_lembar_sebelum', 'stok_lembar_sesudah']);
    });
}
};
