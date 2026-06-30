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
        Schema::create('validasi_graji_balken', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_graji_balken')
                    ->constrained('produksi_graji_balken')
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
        Schema::dropIfExists('validasi_graji_balken');
    }
};
