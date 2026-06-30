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
        Schema::create('list_pekerjaan_menumpuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_hasil_pilih_plywood')->constrained('hasil_pilih_plywood')->onDelete('cascade');
            $table->integer('jumlah_asal'); // Jumlah awal yang harus direparasi
            $table->integer('jumlah_selesai')->default(0);
            $table->integer('jumlah_belum_selesai');
            $table->enum('status', ['selesai', 'belum selesai'])->default('belum selesai');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_pekerjaan_menumpuk');
    }
};
