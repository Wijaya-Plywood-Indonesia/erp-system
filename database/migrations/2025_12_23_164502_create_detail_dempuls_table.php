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
        Schema::create('detail_dempuls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_dempul')
                ->constrained('produksi_dempuls')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_rencana_pegawai_dempul')
                ->constrained('rencana_pegawai_dempuls')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_barang_setengah_jadi_hp')
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('modal');
            $table->integer('hasil');
            $table->integer('nomor_palet')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_dempuls');
    }
};
