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
        Schema::table('barang_setengah_jadi_hp', function (Blueprint $table) {
            $table->string('merek', 255)->nullable()->default(null)->after('harga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barang_setengah_jadi_hp', function (Blueprint $table) {
            $table->dropColumn('merek');
        });
    }
};
