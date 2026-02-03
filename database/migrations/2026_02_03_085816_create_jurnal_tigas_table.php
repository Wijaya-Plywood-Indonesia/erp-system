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
            $table->decimal('kubikasi')->nullable();
            $table->integer('harga')->nullable();
            $table->integer('total')->nullable();
            $table->string('createdBy');
            $table->string('status');
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
