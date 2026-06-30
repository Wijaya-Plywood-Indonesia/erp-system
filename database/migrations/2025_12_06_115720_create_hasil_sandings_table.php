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
        Schema::create('hasil_sandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_sanding')
                ->nullable()
                ->constrained('produksi_sandings')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_barang_setengah_jadi')
                ->nullable()
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('kuantitas');

            $table->integer('jumlah_sanding_face')->nullable();
            $table->integer('jumlah_sanding_back')->nullable();
            $table->integer('no_palet')->nullable();
            $table->string('status');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_sandings');
    }
};
