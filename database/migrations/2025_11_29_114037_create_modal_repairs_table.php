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
        Schema::create('modal_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_repair')
                ->constrained('produksi_repairs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_ukuran')
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('jumlah');
            $table->string('kw');
            $table->integer('nomor_palet');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modal_repairs');
    }
};
