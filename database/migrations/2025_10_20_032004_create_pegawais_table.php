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
        Schema::create('pegawais', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pegawai')->unique();
            $table->string('nama_pegawai');
            $table->text('alamat')->nullable();
            $table->string('no_telepon_pegawai')->nullable();
            $table->boolean('jenis_kelamin_pegawai')->default(0);
            $table->date('tanggal_masuk');
            $table->string('foto')->nullable(); // 🆕 kolom foto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawais');
    }
};
