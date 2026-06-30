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
        Schema::create('supplier_kayus', function (Blueprint $table) {
            $table->id();
            $table->string('nama_supplier');
            $table->string('no_telepon')->nullable();
            $table->string('nik');
            $table->string('upload_ktp')->nullable();
            $table->boolean('jenis_kelamin')->default(0);
            $table->text('alamat')->nullable();
            $table->string('jenis_bank')->nullable();
            $table->string('no_rekening')->nullable();
            $table->boolean('status_supplier')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_kayus');
    }
};
