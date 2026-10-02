<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TargetKediLembarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targets = [
            ['panjang' => 244, 'lebar' => 122, 'tebal' => 0.5, 'target' => 1085],
            ['panjang' => 122, 'lebar' => 244, 'tebal' => 3.7, 'target' => 626],
            ['panjang' => 122, 'lebar' => 130, 'tebal' => 3.7, 'target' => 1290],
            ['panjang' => 48, 'lebar' => 244, 'tebal' => 3.7, 'target' => 170],
            ['panjang' => 68, 'lebar' => 244, 'tebal' => 3.7, 'target' => 170],
            ['panjang' => 122, 'lebar' => 244, 'tebal' => 1.8, 'target' => 1285],
        ];

        // Bongkar id_mesin = 7
        // Masuk ? let's assume same? No, the user data says "Jml Bongkar", "Rata-rata / Bongkar", so it's Kedi Bongkar.
        // Wait, Kedi Bongkar targets. Is there "Kedi Masuk"?
        // Kedi Masuk target is currently "kode_ukuran = 'MASUK'".

        foreach ($targets as $t) {
            $ukuran = \App\Models\Ukuran::where('panjang', $t['panjang'])
                ->where('lebar', $t['lebar'])
                ->where('tebal', $t['tebal'])
                ->first();

            if (!$ukuran) {
                // Try swapping panjang and lebar? Or just create it?
                $ukuran = \App\Models\Ukuran::firstOrCreate([
                    'panjang' => $t['panjang'],
                    'lebar' => $t['lebar'],
                    'tebal' => $t['tebal']
                ], [
                    'kubikasi' => ($t['panjang'] * $t['lebar'] * $t['tebal']) / 1000000,
                    'nama_ukuran' => "{$t['panjang']}mm x {$t['lebar']}mm x {$t['tebal']}mm",
                    'dimensi' => "{$t['panjang']} x {$t['lebar']} x {$t['tebal']}"
                ]);
            }

            \App\Models\Target::updateOrCreate([
                'id_mesin' => 7,
                'id_ukuran' => $ukuran->id,
                'tipe_target' => 'lembar',
                'kode_ukuran' => 'BONGKAR' . $t['panjang'] . $t['lebar'] . $t['tebal']
            ], [
                'target' => $t['target'],
                'orang' => 3,
                'jam' => 10,
                'status' => 'disetujui'
            ]);
        }
    }
}
