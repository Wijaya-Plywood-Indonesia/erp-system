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
        Schema::create('sub_anak_akuns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_anak_akun')
                ->constrained('anak_akuns')
                ->onDelete('cascade');

            $table->string('kode_sub_anak_akun')->unique();
            $table->string('nama_sub_anak_akun');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_anak_akuns');
    }
};
