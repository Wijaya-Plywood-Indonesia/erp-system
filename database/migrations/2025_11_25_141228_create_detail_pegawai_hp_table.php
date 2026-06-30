<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * * ATURAN UP: Tabel Induk (produksi_hp, mesins, pegawais) 
     * harus sudah dibuat sebelum migrasi ini dijalankan.
     */
    public function up(): void
    {
        if (!Schema::hasTable('detail_pegawai_hp')) {
            Schema::create('detail_pegawai_hp', function (Blueprint $table) {
                $table->id();

                // Referensi ke Induk Produksi HP
                $table->foreignId('id_produksi_hp')
                    ->constrained('produksi_hp')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                // Referensi ke Induk Mesin
                $table->foreignId('id_mesin')
                    ->constrained('mesins')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                // Referensi ke Induk Pegawai
                $table->foreignId('id_pegawai')
                    ->constrained('pegawais')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->text('tugas');
                $table->time('masuk');
                $table->time('pulang');
                $table->string('ijin')->nullable();
                $table->string('ket')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     * * CATATAN: Jika rollback error pada tabel induk, pastikan timestamp 
     * file migrasi ini LEBIH BESAR daripada file migrasi tabel induk.
     */
    public function down(): void
    {
        // Matikan pengecekan foreign key agar tidak "stuck" jika urutan file salah
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('detail_pegawai_hp')) {
            Schema::table('detail_pegawai_hp', function (Blueprint $table) {
                // Menghapus foreign key secara eksplisit untuk membersihkan memori database
                $table->dropForeign(['id_produksi_hp']);
                $table->dropForeign(['id_mesin']);
                $table->dropForeign(['id_pegawai']);
            });
        }

        Schema::dropIfExists('detail_pegawai_hp');

        // Hidupkan kembali pengecekan setelah tabel anak berhasil dibuang
        Schema::enableForeignKeyConstraints();
    }
};
