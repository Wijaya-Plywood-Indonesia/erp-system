<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('induk_akuns', function (Blueprint $table) {
            $table->id();
            $table->string('kode_induk_akun')->unique();
            $table->string('nama_induk_akun');
            $table->text('keterangan')->nullable();
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('induk_akuns');
    }
};
