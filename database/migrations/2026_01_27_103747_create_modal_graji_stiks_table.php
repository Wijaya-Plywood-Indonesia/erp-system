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
        Schema::create('modal_graji_stiks', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel graji_stiks
            $table->foreignId('id_graji_stiks')
                ->constrained('graji_stiks') // Pastikan nama tabel tujuan tepat (pakai 's')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Relasi ke tabel ukuran
            $table->foreignId('id_ukuran')
                ->constrained('ukurans') // Pastikan tabel 'ukuran' sudah ada di database
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('jumlah_bahan');
            $table->integer('nomor_palet');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modal_graji_stiks');
    }
};
