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
        Schema::create('pegawai_graji_stiks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_graji_stiks')
                ->constrained('graji_stiks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->string('ijin');
            $table->string('keterangan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai_graji_stiks');
    }
};
