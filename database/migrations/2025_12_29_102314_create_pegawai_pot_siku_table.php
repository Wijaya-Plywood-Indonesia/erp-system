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
        Schema::create('pegawai_pot_siku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pot_siku')
                    ->constrained('produksi_pot_siku')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                    ->constrained('pegawais')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            $table->time('masuk');
            $table->time('pulang');
            $table->string('tugas');
            $table->string('ijin')->nullable();
            $table->string('ket')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_pot_siku');
    }
};
