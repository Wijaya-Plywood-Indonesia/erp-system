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
        Schema::create('modal_pilih_veneer_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_modal_pilih_veneer')
                ->constrained('modal_pilih_veneer')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_pegawai_pilih_veneer')
                ->constrained('pegawai_pilih_veneer')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modal_pilih_veneer_pegawai');
    }
};
