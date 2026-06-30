<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot 
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'stik', 'lahan_rotary')");
            $table->string('diterima_oleh')->default('-')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot 
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'stik')");

            $table->string('diterima_oleh')->default('Belum Diterima')->change();
        });
    }
};
