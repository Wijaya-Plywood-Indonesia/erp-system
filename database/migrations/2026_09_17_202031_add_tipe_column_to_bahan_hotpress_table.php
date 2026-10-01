<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->enum('tipe', ['pemakaian', 'pengembalian'])
                ->default('pemakaian')
                ->after('sumber');
        });
    }

    public function down(): void
    {
        Schema::table('bahan_hotpress', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};