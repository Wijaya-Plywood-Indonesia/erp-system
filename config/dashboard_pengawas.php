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

    /*
    | Banner peringatan potongan gaji di Dashboard Pengawas.
    | Tampil di paling atas pada hari Jumat sebelum `jam_akhir`, berisi
    | rekap potongan per orang untuk siklus Jumat-Kamis yang baru selesai.
    */
    'peringatan_potongan' => [
        'aktif' => env('DASHBOARD_POTONGAN_AKTIF', true),
        // true = tampil kapan saja (untuk testing di local), jangan aktif di produksi
        'paksa_tampil' => env('DASHBOARD_POTONGAN_PAKSA', false),
        'batas' => 50000,   // warning jika total potongan per orang > batas
        'jam_akhir' => 12,  // "pagi" = sebelum jam ini (waktu Asia/Jakarta)
        'cache_menit' => 10,

        // Label section dashboard => key lini potongan (LiniProduksiResolver).
        // Section yang tidak ada di sini tidak menampilkan banner.
        'divisi_lini' => [
            'Rotary' => 'Rotary',
            'Press Dryer' => 'Dryer',
            'Stik' => 'Stik',
            'Kedi' => 'Kedi',
            'Repair' => 'Repair',
            'Joint' => 'Joint',
            'Sanding Joint' => 'Sanding Joint',
            'Pot AF Joint' => 'Pot Afalan Joint',
            'Pot Siku' => 'Pot Siku',
            'Pot Jelek' => 'Pot Jelek',
            'Pilih Veneer' => 'Pilih Veneer',
        ],
    ],
];
