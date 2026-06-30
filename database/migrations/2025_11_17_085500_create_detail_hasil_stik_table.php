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
        Schema::create('detail_hasil_stik', function (Blueprint $table) {
            $table->id();
            $table->integer('no_palet');
            $table->integer('kw');
            $table->integer('total_lembar');
            $table->foreignId('id_ukuran')
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_produksi_stik')
                ->constrained('produksi_stik')
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
        Schema::dropIfExists('detail_hasil_stik');
    }
};
