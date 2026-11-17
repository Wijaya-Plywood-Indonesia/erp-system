<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah rincian komponen HPP (kayu, pekerja, mesin, bahan penolong) + asal lahan + asal mesin
 * pada veneer kering dan veneer jadi.
 *
 * Kolom `komposisi` (JSON) berisi:
 *   { "komponen": {"kayu": 966667, "pekerja_rotary": ..., ...},   // Rp per m³
 *     "lahan":    {"12": 0.62, "15": 0.38},                        // porsi m³ per id_lahan (total 1)
 *     "mesin":    {"dryer": 0.67, "kedi": 0.33} }                  // porsi m³ per jenis mesin kering
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Veneer kering: tiap baris MASUK dari produksi menyimpan rinciannya ─────────
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            if (! Schema::hasColumn('stok_veneer_kerings', 'tipe_mesin')) {
                $table->string('tipe_mesin', 10)->nullable()->after('kw')
                    ->comment('dryer | kedi | null (opname/mutasi/nota)');
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'komposisi')) {
                $table->json('komposisi')->nullable();
            }
        });

        // ── Log veneer jadi: hpp_kayu & hpp_mesin sudah ada di $casts model tapi belum di tabel ──
        Schema::table('hpp_veneer_jadi_log', function (Blueprint $table) {
            if (! Schema::hasColumn('hpp_veneer_jadi_log', 'hpp_kayu')) {
                $table->decimal('hpp_kayu', 20, 2)->nullable()->after('total_kubikasi');
            }
            if (! Schema::hasColumn('hpp_veneer_jadi_log', 'hpp_mesin')) {
                $table->decimal('hpp_mesin', 20, 2)->nullable()->after('hpp_pekerja');
            }
            if (! Schema::hasColumn('hpp_veneer_jadi_log', 'hpp_lainnya')) {
                $table->decimal('hpp_lainnya', 20, 2)->nullable()->after('hpp_bahan_penolong')
                    ->comment('bagian HPP tanpa rincian (stok awal/opname/nota)');
            }
            if (! Schema::hasColumn('hpp_veneer_jadi_log', 'jalur')) {
                $table->string('jalur', 30)->nullable()->after('tipe_transaksi')
                    ->comment('kering_langsung | repair | joint | gudang_jadi | pilih | opname');
            }
            if (! Schema::hasColumn('hpp_veneer_jadi_log', 'komposisi')) {
                $table->json('komposisi')->nullable();
            }
        });

        // ── Stok veneer jadi: komposisi rata-rata stok saat ini ────────────────────────
        Schema::table('stok_veneer_jadi', function (Blueprint $table) {
            if (! Schema::hasColumn('stok_veneer_jadi', 'hpp_kayu_last')) {
                $table->decimal('hpp_kayu_last', 20, 2)->nullable()->after('hpp_average');
            }
            if (! Schema::hasColumn('stok_veneer_jadi', 'hpp_mesin_last')) {
                $table->decimal('hpp_mesin_last', 20, 2)->nullable()->after('hpp_pekerja_last');
            }
            if (! Schema::hasColumn('stok_veneer_jadi', 'komposisi')) {
                $table->json('komposisi')->nullable();
            }
        });

        // ── Gudang veneer jadi (antrean "belum diterima") ──────────────────────────────
        Schema::table('gudang_veneer_jadis', function (Blueprint $table) {
            if (! Schema::hasColumn('gudang_veneer_jadis', 'hpp_kayu_last')) {
                $table->decimal('hpp_kayu_last', 20, 2)->nullable();
            }
            if (! Schema::hasColumn('gudang_veneer_jadis', 'hpp_mesin_last')) {
                $table->decimal('hpp_mesin_last', 20, 2)->nullable();
            }
            if (! Schema::hasColumn('gudang_veneer_jadis', 'jalur')) {
                $table->string('jalur', 30)->nullable();
            }
            if (! Schema::hasColumn('gudang_veneer_jadis', 'komposisi')) {
                $table->json('komposisi')->nullable();
            }
        });
    }

    public function down(): void
    {
        $drop = [
            'stok_veneer_kerings' => ['tipe_mesin', 'komposisi'],
            'hpp_veneer_jadi_log' => ['hpp_kayu', 'hpp_mesin', 'hpp_lainnya', 'jalur', 'komposisi'],
            'stok_veneer_jadi'    => ['hpp_kayu_last', 'hpp_mesin_last', 'komposisi'],
            'gudang_veneer_jadis' => ['hpp_kayu_last', 'hpp_mesin_last', 'jalur', 'komposisi'],
        ];

        foreach ($drop as $tabel => $kolom) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel, $kolom) {
                foreach ($kolom as $k) {
                    if (Schema::hasColumn($tabel, $k)) {
                        $table->dropColumn($k);
                    }
                }
            });
        }
    }
};