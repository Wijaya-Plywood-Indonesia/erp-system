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
        Schema::create('lain_lain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_detail_lain_lain')
                ->constrained('detail_lain_lains')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->time('masuk');
            $table->time('pulang');
            $table->string('ijin')->nullable();
            $table->string('ket')->nullable();
            $table->string('hasil')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lain_lain');
    }
};
