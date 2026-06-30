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
        Schema::create('produksi_rotaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_mesin')
                ->constrained('mesins')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('tgl_produksi');
            $table->integer('jam_kerja')->default(10); // Kolom baru

            $table->text('kendala')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_rotaries');
    }
};
