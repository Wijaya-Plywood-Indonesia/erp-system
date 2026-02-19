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
        Schema::create('jurnal_tigas', function (Blueprint $table) {
            $table->id();
            $table->integer('modif1000');
            $table->integer('akun_seratus');
            $table->string('detail')->nullable();
            $table->integer('banyak')->nullable();
            $table->decimal('kubikasi', 12, 4)->nullable();
            $table->decimal('harga', 18, 2)->nullable();
            $table->decimal('total', 18, 2)->nullable();
            $table->string('createdBy');
            $table->string('status')->default('belum sinkron');
            $table->string('synchronized_by')->nullable(); // Nama petugas yang menekan tombol sync
            $table->dateTime('synchronized_at')->nullable(); // Waktu presisi saat disinkronkan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal_tigas');
    }
};
