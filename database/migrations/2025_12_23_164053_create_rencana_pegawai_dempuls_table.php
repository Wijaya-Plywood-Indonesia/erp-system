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
        Schema::create('rencana_pegawai_dempuls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_dempul')
                ->constrained('produksi_dempuls')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

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
        Schema::dropIfExists('rencana_pegawai_dempuls');
    }
};
