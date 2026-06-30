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
            $table->foreignId('id_veneer_mutasi')->nullable()->constrained('veneer_mutasis')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->dropForeign(['id_veneer_mutasi']);
            $table->dropColumn('id_veneer_mutasi');
        });
    }
};
