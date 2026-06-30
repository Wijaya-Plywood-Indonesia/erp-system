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
        Schema::create('produksi_press_dryers', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_produksi');
            $table->string('shift');
            $table->text('kendala')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('produksi_press_dryers');

        Schema::enableForeignKeyConstraints();
    }
};
