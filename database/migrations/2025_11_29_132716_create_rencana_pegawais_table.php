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
        Schema::create('rencana_pegawais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_repair')
                ->constrained('produksi_repairs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('nomor_meja');
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->string('ijin')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rencana_pegawais');
    }
};