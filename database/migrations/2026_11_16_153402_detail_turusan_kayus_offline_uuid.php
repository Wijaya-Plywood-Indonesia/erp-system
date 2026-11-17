<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->uuid('offline_uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->dropUnique(['offline_uuid']);
            $table->dropColumn('offline_uuid');
        });
    }
};
