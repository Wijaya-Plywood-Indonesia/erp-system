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
        DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot 
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'stik')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot 
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'kedi')");
    }
};
