<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Target Sanding Besar/Kecil sebelumnya per-ukuran (per tebal + kategori),
 * padahal target dari pengawas ternyata flat per mesin + shift:
 *   - Sanding Besar Pagi : 350
 *   - Sanding Besar Malam: 420
 *   - Sanding Kecil Pagi : 230
 *   - Sanding Kecil Malam: 320
 *
 * Migration ini menghapus semua baris target lama untuk id_mesin 17
 * (Sanding Besar) & 18 (Sanding Kecil) - baik yang default maupun yang
 * override kategori "Platform" - lalu menggantinya dengan 4 baris flat
 * di atas. id_ukuran dan id_jenis_kayu diisi placeholder (sama seperti
 * pola "Nebeli" pada target Hotpress) karena sudah tidak dipakai untuk
 * pencocokan target Sanding.
 */
return new class extends Migration
{
    private const ID_MESIN_BESAR = 17;

    private const ID_MESIN_KECIL = 18;

    private const ID_UKURAN_PLACEHOLDER = 33; // ukuran 0 x 0 x 0

    private const ID_JENIS_KAYU_PLACEHOLDER = 1;

    public function up(): void
    {
        DB::table('targets')
            ->whereIn('id_mesin', [self::ID_MESIN_BESAR, self::ID_MESIN_KECIL])
            ->delete();

        $rows = [
            [
                'id_mesin' => self::ID_MESIN_BESAR,
                'shift' => 'PAGI',
                'kode_ukuran' => 'SANDING BESAR PAGI',
                'target' => 350,
                'orang' => 1,
            ],
            [
                'id_mesin' => self::ID_MESIN_BESAR,
                'shift' => 'MALAM',
                'kode_ukuran' => 'SANDING BESAR MALAM',
                'target' => 420,
                'orang' => 1,
            ],
            [
                'id_mesin' => self::ID_MESIN_KECIL,
                'shift' => 'PAGI',
                'kode_ukuran' => 'SANDING KECIL PAGI',
                'target' => 230,
                'orang' => 4,
            ],
            [
                'id_mesin' => self::ID_MESIN_KECIL,
                'shift' => 'MALAM',
                'kode_ukuran' => 'SANDING KECIL MALAM',
                'target' => 320,
                'orang' => 4,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('targets')->insert([
                'id_mesin' => $row['id_mesin'],
                'id_ukuran' => self::ID_UKURAN_PLACEHOLDER,
                'id_jenis_kayu' => self::ID_JENIS_KAYU_PLACEHOLDER,
                'id_kategori_barang' => null,
                'shift' => $row['shift'],
                'grade' => null,
                'kode_ukuran' => $row['kode_ukuran'],
                'target' => $row['target'],
                'orang' => $row['orang'],
                'jam_mulai' => null,
                'jam_selesai' => null,
                'jam' => 9,
                'gaji' => 115000.00,
                'status' => 'diajukan',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('targets')
            ->whereIn('id_mesin', [self::ID_MESIN_BESAR, self::ID_MESIN_KECIL])
            ->whereIn('kode_ukuran', [
                'SANDING BESAR PAGI',
                'SANDING BESAR MALAM',
                'SANDING KECIL PAGI',
                'SANDING KECIL MALAM',
            ])
            ->delete();

        // Catatan: baris lama per-ukuran yang dihapus di up() TIDAK
        // dikembalikan oleh down() ini. Jika perlu rollback penuh,
        // restore dari backup database sebelum migration dijalankan.
    }
};