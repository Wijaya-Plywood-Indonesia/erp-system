<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_gudang_satu', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_hasil_pilih_plywood')
                ->constrained('hasil_pilih_plywood')
                ->cascadeOnDelete();

            $table->foreignId('id_produksi_terima_gudang_satu')
                ->nullable()
                ->constrained('produksi_terima_gudang_satu')
                ->nullOnDelete();

            $table->string('diserahkan_oleh')->nullable();
            $table->string('diterima_oleh')->default('-');
            $table->string('status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_gudang_satu');
    }
};
