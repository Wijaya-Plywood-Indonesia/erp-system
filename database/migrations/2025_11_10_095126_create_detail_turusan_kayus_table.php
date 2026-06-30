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
        Schema::create('detail_turusan_kayus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kayu_masuk')
                ->nullable()
                ->constrained('kayu_masuks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('nomer_urut');
            $table->foreignId('lahan_id')
                ->nullable()
                ->constrained('lahans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('jenis_kayu_id')
                ->nullable()
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('panjang');
            $table->integer('grade');
            $table->integer('diameter');
            $table->integer('kuantitas')->default('1');
            $table->timestamps();
            // Kolom tambahan created_by & updated_by
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_turusan_kayus');
    }
};
