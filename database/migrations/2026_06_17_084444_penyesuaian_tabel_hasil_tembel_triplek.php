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
        // 1. Menghapus kolom id_pegawai_tembel_triplek (beserta Foreign Key-nya) dari tabel lama
        Schema::table('hasil_tembel_triplek', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pegawai_tembel_triplek');
        });

        // 2. Membuat tabel pivot baru untuk multiple pegawai
        Schema::create('hasil_tembel_triplek_pegawai', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('id_hasil_tembel_triplek')
                ->constrained('hasil_tembel_triplek')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('id_pegawai')
                ->constrained('pegawais')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Menghapus tabel pivot jika dilakukan rollback
        Schema::dropIfExists('hasil_tembel_triplek_pegawai');

        // Mengembalikan kolom lama jika dilakukan rollback
        Schema::table('hasil_tembel_triplek', function (Blueprint $table) {
            $table->foreignId('id_pegawai_tembel_triplek')
                ->nullable()
                ->constrained('pegawai_tembel_triplek')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};