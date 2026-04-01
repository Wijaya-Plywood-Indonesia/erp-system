<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_detail_hasil_palet_rotary')
                ->constrained('detail_hasil_palet_rotaries', 'id', 'dhpr_st_pivot_dhpr_id_foreign')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('diserahkan_oleh');
            $table->string('diterima_oleh')->default('Belum Diterima');
            $table->enum('tipe', ['rotary', 'dryer', 'kedi']);  // rotary=serah, dryer/kedi=terima
            $table->string('status');                            // 'Serah Barang' | 'Terima Barang'
            $table->timestamps();

            // Satu palet hanya bisa satu kali per tipe
            // Mencegah dryer/kedi terima palet yang sama dua kali
            $table->unique(['id_detail_hasil_palet_rotary', 'tipe'], 'unique_palet_per_tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_hasil_palet_rotary_serah_terima_pivot');
    }
};
