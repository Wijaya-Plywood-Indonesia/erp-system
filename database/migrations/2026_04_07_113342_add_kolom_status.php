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
        Schema::table('tempat_kayus', function (Blueprint $table) {
            $table->string('status')->nullable()->after('diserahkan_oleh')->default('belum serah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tempat_kayus', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
