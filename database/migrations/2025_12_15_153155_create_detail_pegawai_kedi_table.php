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
        Schema::create('detail_pegawai_kedi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_kedi')
                ->constrained('produksi_kedi')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_mesin')
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->text('tugas');
            $table->time('masuk');
            $table->time('pulang');
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
        Schema::dropIfExists('detail_pegawai_kedi');
    }
};
