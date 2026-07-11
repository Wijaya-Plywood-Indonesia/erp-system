<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_dempuls', function (Blueprint $table) {
            $table->string('diserahkan_oleh')->nullable();
            $table->timestamp('diserahkan_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detail_dempuls', function (Blueprint $table) {
            $table->dropColumn(['diserahkan_oleh', 'diserahkan_at']);
        });
    }
};
