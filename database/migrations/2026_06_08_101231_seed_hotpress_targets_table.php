<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ID Mesin Hotpress
        $hotpressMachineIds = [13, 26, 27, 28]; // HOTPRESS, HOTPRESS 1, HOTPRESS 2, HOTPRESS 3
        
        // Ambil ID ukuran 0x0x0
        $ukuran0 = DB::table('ukurans')->where('panjang', 0)->where('lebar', 0)->where('tebal', 0)->first();
        $idUkuran0 = $ukuran0 ? $ukuran0->id : 33;

        $targets = [
            // 1. Plywood 5 mm Kayu Sengon -> Target: 3900 (average of 3600 - 4200)
            [
                'id_ukuran' => 101, // 122x244x5
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 3900,
                'kode_ukuran' => 'HOTPRESS1222445sPlywood',
            ],
            // 2. Platform 8 mm -> Target: 1500
            [
                'id_ukuran' => 103, // 122x244x8
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1500,
                'kode_ukuran' => 'HOTPRESS1222448sPlatform',
            ],
            // 3. Platform 9 mm -> Target: 1600 (average of 1500 - 1700)
            [
                'id_ukuran' => 104, // 122x244x9
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1600,
                'kode_ukuran' => 'HOTPRESS1222449sPlatform',
            ],
            [
                'id_ukuran' => 23, // 244x122x9
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1600,
                'kode_ukuran' => 'HOTPRESS2441229sPlatform',
            ],
            // 4. Platform 12 mm -> Target: 1100 (average of 1000 - 1200)
            [
                'id_ukuran' => 105, // 122x244x12
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1100,
                'kode_ukuran' => 'HOTPRESS12224412sPlatform',
            ],
            [
                'id_ukuran' => 24, // 244x122x12
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1100,
                'kode_ukuran' => 'HOTPRESS24412212sPlatform',
            ],
            // 5. Platform 15 mm -> Target: 900 (average of 850 - 950)
            [
                'id_ukuran' => 106, // 122x244x15
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 900,
                'kode_ukuran' => 'HOTPRESS12224415sPlatform',
            ],
            [
                'id_ukuran' => 25, // 244x122x15
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 900,
                'kode_ukuran' => 'HOTPRESS24412215sPlatform',
            ],
            // 6. Platform 18 mm -> Target: 875 (average of 850 - 900)
            [
                'id_ukuran' => 107, // 122x244x18
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 875,
                'kode_ukuran' => 'HOTPRESS12224418sPlatform',
            ],
            [
                'id_ukuran' => 26, // 244x122x18
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 875,
                'kode_ukuran' => 'HOTPRESS24412218sPlatform',
            ],
            // 7. Nebeli -> Target: 1850 (average of 1700 - 2000)
            // (Dipetakan ke id_ukuran 0x0x0)
            [
                'id_ukuran' => $idUkuran0, 
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 1850,
                'kode_ukuran' => 'HOTPRESS12224411sNebeli',
            ],
            [
                'id_ukuran' => $idUkuran0, 
                'id_jenis_kayu' => 3, // Meranti (m)
                'target' => 1850,
                'kode_ukuran' => 'HOTPRESS12224411mNebeli',
            ],
            // 8. Platform 12 mm PG -> Target: 475 (average of 450 - 500)
            [
                'id_ukuran' => 105, // 122x244x12
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 475,
                'kode_ukuran' => 'HOTPRESS12224412sPlatformPG',
            ],
            [
                'id_ukuran' => 24, // 244x122x12
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 475,
                'kode_ukuran' => 'HOTPRESS24412212sPlatformPG',
            ],
            // 9. Plywood 9 mm Kayu Sengon Grade AJ -> Target: 625 (average of 600 - 650)
            [
                'id_ukuran' => 104, // 122x244x9
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 625,
                'kode_ukuran' => 'HOTPRESS1222449sPlywoodAJ',
            ],
            [
                'id_ukuran' => 23, // 244x122x9
                'id_jenis_kayu' => 1, // Sengon (s)
                'target' => 625,
                'kode_ukuran' => 'HOTPRESS2441229sPlywoodAJ',
            ],
        ];

        // Hapus semua data targets terlebih dahulu agar hanya tersisa target hotpress di atas
        DB::table('targets')->delete();

        foreach ($hotpressMachineIds as $machineId) {
            foreach ($targets as $data) {
                // Masukkan data baru
                DB::table('targets')->insert([
                    'id_mesin' => $machineId,
                    'id_ukuran' => $data['id_ukuran'],
                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                    'kode_ukuran' => $data['kode_ukuran'],
                    'target' => $data['target'],
                    'orang' => 10,
                    'jam' => 10,
                    'gaji' => 115000.00,
                    'status' => 'diajukan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hotpressMachineIds = [13, 26, 27, 28];
        $kodeUkurans = [
            'HOTPRESS1222445sPlywood',
            'HOTPRESS1222448sPlatform',
            'HOTPRESS1222449sPlatform',
            'HOTPRESS2441229sPlatform',
            'HOTPRESS12224412sPlatform',
            'HOTPRESS24412212sPlatform',
            'HOTPRESS12224415sPlatform',
            'HOTPRESS24412215sPlatform',
            'HOTPRESS12224418sPlatform',
            'HOTPRESS24412218sPlatform',
            'HOTPRESS12224411sNebeli',
            'HOTPRESS12224411mNebeli',
            'HOTPRESS12224412sPlatformPG',
            'HOTPRESS24412212sPlatformPG',
            'HOTPRESS1222449sPlywoodAJ',
            'HOTPRESS2441229sPlywoodAJ',
        ];

        DB::table('targets')
            ->whereIn('id_mesin', $hotpressMachineIds)
            ->whereIn('kode_ukuran', $kodeUkurans)
            ->delete();
    }
};
