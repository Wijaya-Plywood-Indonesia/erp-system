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
        Schema::create('produksi_sandings', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('shift')->nullable();
            $table->string('kendala')->nullable();
            $table->foreignId('id_mesin')
                ->constrained('mesins')
                ->nullable()
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

        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('produksi_sandings')) {
            Schema::table('produksi_sandings', function (Blueprint $table) {
                // Menghapus foreign key secara eksplisit untuk membersihkan memori database
                $table->dropForeign(['id_mesin']);
            });
        }
        Schema::dropIfExists('produksi_sandings');
    }
};
