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
        if (!Schema::hasTable('grades')) {
            Schema::create('grades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_kategori_barang')
                    ->nullable()
                    ->constrained('kategori_barang')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
                $table->string('nama_grade');
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

        Schema::dropIfExists('grades');

        Schema::enableForeignKeyConstraints();
    }
};
