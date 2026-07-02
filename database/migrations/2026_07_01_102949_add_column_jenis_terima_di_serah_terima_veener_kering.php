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
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->string('jenis_terima')
                ->nullable()
                ->after('diterima_oleh'); // 'kering' | 'jadi'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropColumn('jenis_terima');
        });
    }
};
