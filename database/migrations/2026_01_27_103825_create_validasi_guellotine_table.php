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
        Schema::create('validasi_guellotine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_guellotine')
                    ->constrained('produksi_guellotine')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            $table->string('role');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validasi_guellotine');
    }
};
