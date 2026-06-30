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
        Schema::create('hasil_pilih_veneer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pilih_veneer')
                ->constrained('produksi_pilih_veneer')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_modal_pilih_veneer')
                ->constrained('modal_pilih_veneer')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('kw');
            $table->integer('no_palet');
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_pilih_veneer');
    }
};
