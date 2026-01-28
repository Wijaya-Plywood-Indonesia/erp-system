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
        Schema::create('pegawai_pilih_veneer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pilih_veneer')
                    ->constrained('produksi_pilih_veneer')
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
            $table->text('ket')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_pilih_veneer');
    }
};
