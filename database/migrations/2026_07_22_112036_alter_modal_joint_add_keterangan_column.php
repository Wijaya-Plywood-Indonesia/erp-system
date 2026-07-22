<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modal_joint', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('modal_joint', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
