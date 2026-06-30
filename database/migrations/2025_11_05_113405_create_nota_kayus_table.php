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
        Schema::create('nota_kayus', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_kayu_masuk')
                ->nullable()
                ->constrained('kayu_masuks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('no_nota');
            $table->string('penanggung_jawab')->nullable();
            //  $table->string('pemilik_kayu');
            $table->string('penerima')->nullable();

            $table->string('satpam')->nullable();
            $table->string('status')->default('Belum Diperiksa');
            ;

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_kayus');
    }
};
