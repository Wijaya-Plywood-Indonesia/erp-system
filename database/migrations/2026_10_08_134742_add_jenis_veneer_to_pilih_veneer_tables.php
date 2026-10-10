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
        Schema::table('modal_pilih_veneer', function (Blueprint $table) {
            $table->string('jenis_veneer')->default('jadi')->after('id_produksi_pilih_veneer');
            $table->foreignId('id_stok_veneer_kering')->nullable()->after('id_stok_veneer_jadi')->constrained('stok_veneer_kerings')->nullOnDelete();
        });

        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            $table->string('jenis_veneer')->default('jadi')->after('id_modal_pilih_veneer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modal_pilih_veneer', function (Blueprint $table) {
            $table->dropForeign(['id_stok_veneer_kering']);
            $table->dropColumn('id_stok_veneer_kering');
            $table->dropColumn('jenis_veneer');
        });
        
        Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
            $table->dropColumn('jenis_veneer');
        });
    }
};
