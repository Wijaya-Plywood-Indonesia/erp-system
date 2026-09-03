<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plywood_mutasi_details', function (Blueprint $table) {
            $table->decimal('harga', 15, 2)->nullable()->after('m3');
        });
    }

    public function down(): void
    {
        Schema::table('plywood_mutasi_details', function (Blueprint $table) {
            $table->dropColumn('harga');
        });
    }
};
