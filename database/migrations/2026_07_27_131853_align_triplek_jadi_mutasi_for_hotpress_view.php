<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan struktur tabel mutasi keluar triplek jadi dengan veneer/platform,
 * supaya bisa ikut ditarik oleh VIEW serah_terima_masuk_hp.
 *
 * View mereferensikan:
 *   - mk.id_produksi_hp   (tabel triplek_jadi_mutasi_keluars)
 *   - mk.keterangan       (tabel triplek_jadi_mutasi_keluars) — biasanya sudah ada
 *   - p.diterima_by       (tabel triplek_jadi_mutasi_keluar_palets)
 *   - p.diterima_at       (tabel triplek_jadi_mutasi_keluar_palets)
 *
 * Semua ditambah kondisional (hasColumn) supaya aman kalau sebagian sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triplek_jadi_mutasi_keluars', function (Blueprint $table) {
            if (! Schema::hasColumn('triplek_jadi_mutasi_keluars', 'id_produksi_hp')) {
                // Produksi hotpress yang menerima barang ini (diisi saat diterima).
                $table->foreignId('id_produksi_hp')->nullable()->after('tujuan')
                    ->constrained('produksi_hps')->nullOnDelete();
            }

            if (! Schema::hasColumn('triplek_jadi_mutasi_keluars', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('id_produksi_hp');
            }
        });

        Schema::table('triplek_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            if (! Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'diterima_by')) {
                // Penerimaan hotpress dicatat PER PALET (pola veneer/platform).
                $table->foreignId('diterima_by')->nullable()->after('jumlah_lembar')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'diterima_at')) {
                $table->timestamp('diterima_at')->nullable()->after('diterima_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('triplek_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            if (Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'diterima_by')) {
                $table->dropConstrainedForeignId('diterima_by');
            }
            if (Schema::hasColumn('triplek_jadi_mutasi_keluar_palets', 'diterima_at')) {
                $table->dropColumn('diterima_at');
            }
        });

        Schema::table('triplek_jadi_mutasi_keluars', function (Blueprint $table) {
            if (Schema::hasColumn('triplek_jadi_mutasi_keluars', 'id_produksi_hp')) {
                $table->dropConstrainedForeignId('id_produksi_hp');
            }
            // 'keterangan' sengaja TIDAK di-drop di down() — kemungkinan sudah
            // dipakai fitur lain sejak awal. Kalau memang ditambah migrasi ini
            // dan ingin bersih, hapus manual.
        });
    }
};