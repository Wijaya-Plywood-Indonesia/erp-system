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
        Schema::create('masuk_graji_triplek', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_graji_triplek')
                ->constrained('produksi_graji_triplek')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('no_palet');
            $table->foreignId('id_barang_setengah_jadi_hp')
                ->nullable()
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('isi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masuk_graji_triplek');
    }
};
