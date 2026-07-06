<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veneer_kering_mutasi_keluar_palets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mutasi_keluar')
                ->constrained('veneer_kering_mutasi_keluars')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('no_palet');
            $table->decimal('qty', 12, 4); // jumlah lembar di palet ini
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veneer_kering_mutasi_keluar_palets');
    }
};