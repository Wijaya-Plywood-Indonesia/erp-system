<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_hp', function (Blueprint $table) {
            $table->id();

            // Sumber: hasil triplek dari Hotpress
            $table->foreignId('id_triplek_hasil_hp')
                ->constrained('triplek_hasil_hp')
                ->cascadeOnDelete();

            // Tujuan: produksi Graji Triplek yang menerima (null selama masih menunggu)
            $table->foreignId('id_produksi_graji_triplek')
                ->nullable()
                ->constrained('produksi_graji_triplek')
                ->nullOnDelete();

            $table->string('diserahkan_oleh');
            $table->string('diterima_oleh')->default('-');

            $table->enum('status', ['Serah Triplek', 'Terima Triplek'])
                ->default('Serah Triplek');

            $table->timestamps();

            $table->index(['diterima_oleh']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_hp');
    }
};
