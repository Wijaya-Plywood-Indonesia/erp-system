<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_validasi_otomatis', function (Blueprint $table) {
            $table->id();

            // Saklar utama. Default mati supaya tidak ada produksi yang tiba-tiba
            // divalidasi (dan memotong stok) sebelum admin mengaturnya.
            $table->boolean('aktif')->default(false);

            // Jam validasi otomatis (zona waktu aplikasi = Asia/Jakarta).
            $table->time('jam_validasi')->default('11:00:00');

            // 0 = produksi divalidasi di hari yang sama, 1 = keesokan harinya.
            $table->unsignedTinyInteger('hari_setelah')->default(1);

            // Hanya produksi bertanggal >= ini yang divalidasi otomatis
            // (melindungi data lama yang sengaja belum divalidasi).
            $table->date('mulai_tanggal')->nullable();

            // Daftar kunci menu produksi yang diaktifkan, mis. ["stik","sanding"].
            $table->json('menu_aktif')->nullable();

            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        DB::table('pengaturan_validasi_otomatis')->insert([
            'aktif' => false,
            'jam_validasi' => '11:00:00',
            'hari_setelah' => 1,
            'mulai_tanggal' => null,
            'menu_aktif' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_validasi_otomatis');
    }
};
