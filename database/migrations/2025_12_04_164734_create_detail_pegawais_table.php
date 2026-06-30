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
        Schema::create('detail_pegawais', function (Blueprint $table) {
            $table->id();
            $table->string('tugas')->nullable();
            $table->time('masuk');
            $table->time('pulang');
            $table->string('ijin')->nullable();
            $table->string('ket')->nullable();

            $table->foreignId('id_produksi_dryer')
                ->constrained('produksi_press_dryers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
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
        Schema::dropIfExists('detail_pegawais');
    }
};
