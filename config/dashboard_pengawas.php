<?php

return [
    /*
    | Role yang melihat SEMUA divisi.
    */
    'bypass_roles' => [
        'Super Admin',
        'super_admin',
        'super admin -(role&user)',
    ],

    /*
    | Label divisi (SAMA PERSIS dengan getLabel()) => permission.
    | User cukup punya SALAH SATU permission di list untuk melihat divisinya.
    | Permission yang tidak ada di database diabaikan (hasilnya false).
    */
    'akses' => [
        // ---- Veneer ----
        'Rotary' => ['ViewAny:ProduksiRotary', 'View:ProduksiRotary'],
        'Press Dryer' => ['ViewAny:ProduksiPressDryer', 'View:LaporanPressDryer'],
        'Kedi' => ['ViewAny:ProduksiKedi', 'View:LaporanKedi'],
        'Pilih Veneer' => ['ViewAny:ProduksiPilihVeneer'],

        // ---- Hotpress & Joint ----
        'Hotpress' => ['ViewAny:ProduksiHp'],
        'Joint' => ['ViewAny:ProduksiJoint'],
        'Pot AF Joint' => ['ViewAny:ProduksiPotAfJoint'],
        'Sanding Joint' => ['ViewAny:ProduksiSandingJoint'],
        'Pot Siku' => ['ViewAny:ProduksiPotSiku'],
        'Pot Jelek' => ['ViewAny:ProduksiPotJelek'],
        'Nyusup' => ['ViewAny:ProduksiNyusup'],

        // ---- Repair & finishing ----
        'Repair' => ['ViewAny:ProduksiRepair'],
        'Dempul' => ['ViewAny:ProduksiDempul'],
        'Sanding' => ['ViewAny:ProduksiSanding'],
        'Tembel Triplek' => ['ViewAny:ProduksiTembeltriplek', 'ViewAny:ProduksiTembelTriplek'],
        'Pilih Plywood' => ['ViewAny:ProduksiPilihPlywood'],

        // ---- Graji / stik / palet ----
        'Stik' => ['ViewAny:ProduksiStik'],
        'Graji Stik' => ['ViewAny:GrajiStik'],
        'Graji Balken' => ['ViewAny:ProduksiGrajiBalken'],
        'Graji Triplek' => ['ViewAny:ProduksiGrajitriplek', 'ViewAny:ProduksiGrajiTriplek'],
        'Guellotine' => ['ViewAny:ProduksiGuellotine', 'ViewAny:produksi_guellotine'],
        'Buat Palet' => ['ViewAny:ProduksiPalet'],

        // ---- Gudang / kayu ----
        'Terima Gudang Satu' => ['ViewAny:ProduksiTerimaGudangSatu'],
        'Turun Kayu' => ['ViewAny:TurunKayu'],
    ],
];
