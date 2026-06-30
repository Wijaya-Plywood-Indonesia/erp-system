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
        Schema::create('hasil_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_repair')
                ->constrained('produksi_repairs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_rencana_repair')
                ->constrained('rencana_repairs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_repairs');
    }
};
