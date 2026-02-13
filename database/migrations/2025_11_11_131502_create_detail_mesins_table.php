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
        Schema::create('detail_mesin', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_mesin_dryer')->nullable();
            $table->integer('jam_kerja_mesin');
            // Foreign Key ke tabel induk
            $table->foreignId('id_produksi_dryer')
                ->constrained('produksi_press_dryers')
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
        Schema::dropIfExists('detail_mesin');
    }
};
