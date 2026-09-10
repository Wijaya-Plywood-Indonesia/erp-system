<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang_setengah_jadi_hp', function (Blueprint $table) {
            $table->decimal('harga', 15, 2)->nullable()->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('barang_setengah_jadi_hp', function (Blueprint $table) {
            $table->dropColumn('harga');
        });
    }
};
