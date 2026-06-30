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
        //
        Schema::create('jurnal_1st', function (Blueprint $table) {
            $table->id();

            $table->integer('modif10');
            $table->string('no_akun');
            $table->string('nama_akun')->nullable();
            $table->enum('bagian', ['d', 'k']); // debet/kredit
            $table->integer('banyak')->nullable();
            $table->decimal('m3', 12, 4)->nullable();
            $table->decimal('harga', 18, 2)->nullable();
            $table->decimal('total', 18, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
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
        //
        Schema::dropIfExists('jurnal_1st');
    }
};
