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
        Schema::create('pegawai_sandings', function (Blueprint $table) {
            $table->id();

            $table->string('tugas')->nullable();
            $table->time('masuk');
            $table->time('pulang');
            $table->string('ijin')->nullable();
            $table->string('ket')->nullable();

            $table->foreignId('id_produksi_sanding')
                ->nullable()
                ->constrained('produksi_sandings')
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
        Schema::dropIfExists('pegawai_sandings');
    }
};
