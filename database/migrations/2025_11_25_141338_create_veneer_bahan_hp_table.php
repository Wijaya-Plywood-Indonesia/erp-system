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
        Schema::create('veneer_bahan_hp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_hp')
                ->constrained('produksi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_barang_setengah_jadi_hp')
                ->nullable()
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_detail_komposisi')
                ->nullable()
                ->constrained('detail_komposisi')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('no_palet')->nullable();
            $table->integer('isi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veneer_bahan_hp');
    }
};
