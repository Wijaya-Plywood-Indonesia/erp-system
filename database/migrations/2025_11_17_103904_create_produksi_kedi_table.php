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
        Schema::create('produksi_kedi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->text('kendala')->nullable();
            $table->enum('status', ['bongkar', 'masuk']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_kedi');
    }
};
