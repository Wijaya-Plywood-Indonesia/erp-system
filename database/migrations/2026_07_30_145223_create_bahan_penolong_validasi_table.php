<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_penolong_validasi', function (Blueprint $table) {
            $table->id();
            $table->string('produksi_type'); // mis. App\Models\ProduksiHp, App\Models\ProduksiDempul
            $table->unsignedBigInteger('produksi_id');
            $table->timestamp('divalidasi_at');
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['produksi_type', 'produksi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_penolong_validasi');
    }
};