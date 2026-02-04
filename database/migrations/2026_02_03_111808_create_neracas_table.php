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
        Schema::create('neracas', function (Blueprint $table) {
            $table->id();
            $table->integer('akun_seribu');
            $table->string('detail');
            $table->integer('banyak')->nullable();
            $table->decimal('kubikasi')->nullable();
            $table->integer('harga')->nullable();
            $table->integer('total')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neracas');
    }
};
