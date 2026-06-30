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
        Schema::create('jurnal2', function (Blueprint $table) {
            $table->id();
            $table->integer('modif100');
            $table->integer('no_akun');
            $table->string('nama_akun');
            $table->integer('banyak')->nullable();
            $table->decimal('kubikasi', 12, 4)->nullable();
            $table->decimal('harga', 18, 2)->nullable();
            $table->decimal('total', 18, 2)->nullable();
            $table->string('user_id')->nullable();
            $table->string('status_sinkron')->default('belum sinkron');
            $table->dateTime('synced_at')->nullable();
            $table->string('synced_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal2');
    }
};
