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
        Schema::table('detail_barang_dikerjakan', function (Blueprint $table) {
            $table->timestamp('diserahkan_at')->nullable()->after('hasil');
            $table->string('diserahkan_by')->nullable()->after('diserahkan_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_barang_dikerjakan', function (Blueprint $table) {
            $table->dropColumn(['diserahkan_at', 'diserahkan_by']);
        });
    }
};
