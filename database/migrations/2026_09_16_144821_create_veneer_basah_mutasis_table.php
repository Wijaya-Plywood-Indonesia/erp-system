<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veneer_basah_mutasis', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->enum('tujuan', ['dryer', 'kedi']);
            $table->text('keterangan')->nullable();
            $table->string('dibuat_oleh');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veneer_basah_mutasis');
    }
};
