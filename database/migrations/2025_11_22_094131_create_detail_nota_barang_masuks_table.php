<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detail_nota_barang_masuks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_nota_bm')
                ->constrained('nota_barang_masuks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nama_barang');
            $table->integer('jumlah');
            $table->string('satuan');
            $table->text('keterangan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_nota_barang_masuks');
    }
};
