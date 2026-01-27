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
        Schema::create('hasil_graji_stiks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_graji_stiks')
                ->constrained('graji_stiks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_modal_graji_stiks')
                ->constrained('modal_graji_stiks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('hasil_graji');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_graji_stiks');
    }
};
