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
        if (!Schema::hasTable('detail_masuks')) {
            Schema::create('detail_masuks', function (Blueprint $table) {
                $table->id();
                $table->integer('no_palet');
                $table->string('kw');
                $table->string('isi');
                $table->foreignId('id_ukuran')
                    ->nullable()
                    ->constrained('ukurans')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->foreignId('id_jenis_kayu')
                    ->nullable()
                    ->constrained('jenis_kayus')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->foreignId('id_produksi_dryer')
                    ->constrained('produksi_press_dryers')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('detail_masuks');

        Schema::enableForeignKeyConstraints();
    }
};
