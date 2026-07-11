<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_triplek_jadi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_hasil_graji_triplek')
                ->nullable()
                ->constrained('hasil_graji_triplek')
                ->nullOnDelete();

            $table->foreignId('id_hasil_sanding')
                ->nullable()
                ->constrained('hasil_sandings')
                ->nullOnDelete();

            $table->foreignId('id_produksi_pilih_plywood')
                ->nullable()
                ->constrained('produksi_pilih_plywood')
                ->nullOnDelete();

            $table->string('diserahkan_oleh')->nullable();
            $table->string('diterima_oleh')->default('-');
            $table->string('status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serah_terima_triplek_jadi');
    }
};
