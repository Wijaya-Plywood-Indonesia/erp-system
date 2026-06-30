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
        Schema::create('harga_kayus', function (Blueprint $table) {
            $table->id();
            $table->integer('panjang')->default('0');
            $table->decimal('diameter_terkecil', 10, 2)->nullable();
            $table->decimal('diameter_terbesar', 10, 2)->nullable();
            $table->integer('harga_beli');
            $table->integer('grade');
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_kayus');
    }
};
