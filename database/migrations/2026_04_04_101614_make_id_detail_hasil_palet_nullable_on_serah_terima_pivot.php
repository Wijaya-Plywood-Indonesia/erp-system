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
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            // Hapus foreign key dulu sebelum ubah kolom
            $table->dropForeign('dhpr_st_pivot_dhpr_id_foreign');

            // Ubah jadi nullable
            $table->foreignId('id_detail_hasil_palet_rotary')
                ->nullable()
                ->change()
                ->constrained('detail_hasil_palet_rotaries', 'id', 'dhpr_st_pivot_dhpr_id_foreign')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            $table->dropForeign('dhpr_st_pivot_dhpr_id_foreign');

            $table->foreignId('id_detail_hasil_palet_rotary')
                ->nullable(false)
                ->change()
                ->constrained('detail_hasil_palet_rotaries', 'id', 'dhpr_st_pivot_dhpr_id_foreign')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
