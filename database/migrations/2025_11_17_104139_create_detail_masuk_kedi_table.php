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
        Schema::create('detail_masuk_kedi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mesin')
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('no_palet');
            $table->foreignId('id_ukuran')
                ->nullable()
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_kayu')
                ->nullable()
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('kw');
            $table->integer('jumlah');
            $table->date('rencana_bongkar');

            $table->foreignId('id_produksi_kedi')
                ->constrained('produksi_kedi')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_masuk_kedi');
    }
};