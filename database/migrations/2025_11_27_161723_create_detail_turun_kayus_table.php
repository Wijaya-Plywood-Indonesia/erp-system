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
        // Karena Anda sudah menghapus tabel manual di phpMyAdmin,
        // Kita gunakan Schema::create (bukan Schema::table) untuk membuat ulang dari nol.
        Schema::create('detail_turun_kayus', function (Blueprint $table) {
            $table->id();

            // 1. Relasi ke Parent (Header Transaksi)
            $table->foreignId('id_turun_kayu')
                ->nullable()
                ->constrained('turun_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // 2. Relasi ke Kayu Masuk (Wajib ada agar tidak error id_kayu_masuk not found)
            $table->foreignId('id_kayu_masuk')
                ->constrained('kayu_masuks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();


            // 3. Kolom Data Lainnya (Sesuai Form Filament)
            $table->string('status')->default('menunggu');
            $table->string('nama_supir');
            $table->integer('jumlah_kayu');
            $table->string('foto')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_turun_kayus');
    }
};
