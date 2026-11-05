<?php

/*
 * Menu BK / BM baru. Perilaku tiap kategori diatur di sini, bukan di kode.
 * Tahap 1a: hanya BKL (dan BK satu pabrik) yang aktif. BKK menyusul.
 */
return [
    'aktif' => env('BKBM_BARU_AKTIF', false),

    // Nomor mulai (urutan berikutnya = nilai ini + 1) bila belum ada nota sama sekali.
    // Penomoran BK/BM baru mulai dari 0001, terpisah dari nota lama.
    'nomor_awal' => ['bk' => 0, 'bkl' => 0, 'bkk' => 0, 'bm' => 0, 'bml' => 0, 'bmk' => 0],

    'kategori_bk' => [
        'bk'  => ['label' => 'BK – Barang Keluar',            'aktif' => true,  'kembali' => false, 'bm_lawan' => 'bm'],
        'bkk' => ['label' => 'BKK – Barang Keluar (Pinjam)',  'aktif' => false, 'kembali' => true,  'bm_lawan' => 'bmk'],
        'bkl' => ['label' => 'BKL – Barang Kita di Luar',     'aktif' => true,  'kembali' => true,  'bm_lawan' => null, 'bm_kembali' => 'bml'],
    ],

    'kategori_bm' => [
        'bm'  => 'BM – Barang Masuk',
        'bmk' => 'BMK – Barang Masuk (Pinjam)',
        'bml' => 'BML – Barang Kembali dari Luar',
    ],

    'prefix_barang_umum' => 'Barang Umum - ',
];
