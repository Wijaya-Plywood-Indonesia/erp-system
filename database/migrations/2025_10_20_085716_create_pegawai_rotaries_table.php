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
        Schema::create('pegawai_rotaries', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('id_produksi')
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('role')->nullable();

            $table->time('jam_masuk');
            $table->time('jam_pulang');

            // Kolom baru
            $table->string('izin')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_rotaries');
    }
};