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
        Schema::create('stok_plywood_siap_jual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            // Ukuran veneer — kombinasi ini unik
            $table->decimal('panjang', 8, 2); // cm
            $table->decimal('lebar',   8, 2); // cm
            $table->decimal('tebal',   6, 2); // mm
            $table->string('kw_grade')->nullable();

            // Stok saat ini
            $table->integer('stok_lembar')->default(0)->nullable();
            $table->decimal('stok_kubikasi', 15, 6)->default(0)->nullable(); // m³
            $table->foreignId('id_last_log')
                ->nullable()
                ->constrained('hpp_plywood_siap_jual_log')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_plywood_siap_jual');
    }
};
