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
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            $table->string('jenis_kendala')->after('id_produksi');
            $table->text('keterangan')->nullable()->after('jenis_kendala');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            $table->dropColumn(['jenis_kendala','keterangan']);
        });
    }
};
