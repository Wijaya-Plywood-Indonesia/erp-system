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
        Schema::create('pegawai_turun_kayus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_turun_kayu')
                ->constrained('turun_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_turun_kayus');
    }
};
