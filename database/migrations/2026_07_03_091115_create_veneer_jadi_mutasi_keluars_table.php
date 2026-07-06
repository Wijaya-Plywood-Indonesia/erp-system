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
        Schema::create('veneer_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            // Ukuran veneer — kombinasi ini unik
            $table->decimal('panjang', 8, 2); // cm
            $table->decimal('lebar',   8, 2); // cm
            $table->decimal('tebal',   6, 2); // mm
            $table->string('kw_grade')->nullable();
            $table->integer('jumlah_palet');

            $table->integer('stok_lembar')->default(0)->nullable();
            $table->decimal('stok_kubikasi', 15, 6)->default(0)->nullable();
            $table->string('tujuan'); // 'Lini Plywood', 'Ekspor', 'Lokal', 'Afkir'
            $table->foreignId('dikeluarkan_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null'); // Operator pengeluar
            $table->text('keterangan')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veneer_jadi_mutasi_keluars');
    }
};
