<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_pilih_plywood', function (Blueprint $table) {
            // Add the column. It's a foreign key referencing the serah_terima_triplek_jadi table.
            // Using nullable() in case you have existing records that shouldn't be affected immediately.
            $table->foreignId('id_serah_terima_triplek_jadi')
                  ->nullable()
                  ->constrained('serah_terima_triplek_jadi')
                  ->nullOnDelete(); 
        });
    }

    public function down(): void
    {
        Schema::table('bahan_pilih_plywood', function (Blueprint $table) {
            $table->dropForeign(['id_serah_terima_triplek_jadi']);
            $table->dropColumn('id_serah_terima_triplek_jadi');
        });
    }
};