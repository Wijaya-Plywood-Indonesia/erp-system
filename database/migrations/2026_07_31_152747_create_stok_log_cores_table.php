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
        Schema::create('stok_log_cores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->decimal('panjang', 10, 2);
            $table->decimal('stok_qty', 15, 2)->default(0);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('nilai_stok', 15, 2)->default(0);
            $table->foreignId('id_last_log')->nullable();
            $table->timestamps();

            $table->unique(['id_jenis_kayu', 'panjang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stok_log_cores');
    }
};
