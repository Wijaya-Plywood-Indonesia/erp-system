<?php

namespace Database\Seeders;

use App\Models\AkunGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AkunGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Root utama Nereca
        $neraca = AkunGroup::create([
            'nama' => 'Neraca',
            'akun' => [],
        ]);

        // Grup "akun Aktiva"
        $aktiva = AkunGroup::create([
            'nama' => 'Aktiva',
            'parent_id' => $neraca->id,
            'akun' => [],
        ]);

        // Sub-grup "Aset Lancar"
        AkunGroup::create([
            'nama' => 'Aaktiva Lancar',
            'parent_id' => $aktiva->id,
            'akun' => [1100, 1200, 1300],
        ]);

        // Sub-grup "Aset Tetap"
        AkunGroup::create([
            'nama' => 'Aset Tetap',
            'parent_id' => $aktiva->id,
            'akun' => [1400, 1500],
        ]);

        AkunGroup::create([
            'nama' => 'Aset Tetap',
            'parent_id' => $aktiva->id,
            'akun' => [1600, 1700, 1800, 1900],
        ]);


        // Grup "akun Pasiva"
        $pasiva = AkunGroup::create([
            'nama' => 'Pasiva',
            'parent_id' => $neraca->id,
            'akun' => [],
        ]);

        // Sub-grup "Aset Lancar"
        AkunGroup::create([
            'nama' => 'Utang Lancar',
            'parent_id' => $pasiva->id,
            'akun' => [2100, 2200, 2300, 2400, 2500, 2600],
        ]);

        // Sub-grup "Aset Tetap"
        AkunGroup::create([
            'nama' => 'Utang Jangka Panjang',
            'parent_id' => $pasiva->id,
            'akun' => [2700, 2800, 2900],
        ]);

        AkunGroup::create([
            'nama' => 'Ekuitas',
            'parent_id' => $pasiva->id,
            'akun' => [3100, 3200, 3300, 3400, 3500],
        ]);
    }
}
