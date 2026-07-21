<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE serah_terima_veneer_kering
            MODIFY COLUMN tipe_sumber ENUM('dryer', 'kedi', 'gudang', 'sanding_joint', 'gudang_jadi') NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE serah_terima_veneer_kering
            MODIFY COLUMN tipe_sumber ENUM('dryer', 'kedi', 'gudang', 'sanding_joint') NULL
        ");
    }
};
