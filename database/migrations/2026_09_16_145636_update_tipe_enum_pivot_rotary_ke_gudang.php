<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rotary sekarang HANYA serah ke Gudang Veneer Basah.
     * Dryer/Kedi tidak lagi terima langsung dari Rotary — mereka terima
     * dari Gudang lewat tabel serah_terima_veneer_basah (lihat migration lain).
     *
     * 'stik' TIDAK diubah (di luar scope revisi ini, masih pakai alur lama).
     */
    public function up(): void
    {
        // 1. Perluas enum dulu supaya value baru bisa ditulis
        DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'stik', 'gudang_veneer_basah')");

        // 2. Migrasi data lama: baris riwayat tipe='dryer' dianggap
        //    riwayat lama (biarkan apa adanya untuk histori, TIDAK diubah),
        //    hanya perilaku KE DEPAN yang berubah lewat kode aplikasi.
        //    (Tidak ada UPDATE data di sini secara sengaja — histori lama
        //    tetap dibaca sebagai 'dryer' oleh laporan lama bila diperlukan.)

        // 3. Persempit enum: buang opsi 'dryer' dari pilihan BARU
        //    (baris lama dengan value 'dryer' tetap valid disimpan di kolom
        //    enum MySQL selama value itu masih terdaftar, makanya kita
        //    tetap sertakan 'dryer' di enum agar data lama tidak rusak).
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE detail_hasil_palet_rotary_serah_terima_pivot
            MODIFY COLUMN tipe ENUM('rotary', 'dryer', 'stik')");
    }
};
