<?php

/*
 * Menu BK / BM baru. Perilaku tiap kategori diatur di sini, bukan di kode.
 * BKK: BK pinjam -> BMK di pabrik lain (BKK kuning) -> tombol Kembalikan di BMK membuat BKR -> BMR di pabrik asal (BKK hijau).
 */
return [
    'aktif' => env('BKBM_BARU_AKTIF', false),

    // Tombol "Tandai Selesai" manual di BK (hanya super admin).
    // Untuk MEMATIKAN: ganti true jadi false (atau beri // di depan baris 'tandai_selesai_aktif'), lalu deploy.
    // Siapa yang boleh memakai diatur di app/Filament/Resources/Bks/Actions/TandaiSelesaiAction.php (daftar PERAN).
    'tandai_selesai_aktif' => true,

    // Nomor mulai (urutan berikutnya = nilai ini + 1) bila belum ada nota sama sekali.
    // Penomoran BK/BM baru mulai dari 0001, terpisah dari nota lama.
    'nomor_awal' => ['bk' => 0, 'bkl' => 0, 'bkk' => 0, 'bm' => 0, 'bml' => 0, 'bmk' => 0, 'bkr' => 0, 'bmr' => 0],

    // Pengiriman antar pabrik (isi di .env, tanpa nilai bawaan untuk kunci).
    'pabrik' => env('BKBM_PABRIK'),               // mis. Wijaya / Wahana
    'partner_url' => env('BKBM_PARTNER_URL'),     // alamat web pabrik lawan
    'partner_key' => env('BKBM_PARTNER_KEY'),     // kunci rahasia, sama di kedua pabrik
    'kirim_aktif' => env('BKBM_KIRIM_AKTIF', false),

    'kategori_bk' => [
        'bk'  => ['label' => 'BK – Barang Keluar',            'aktif' => true,  'kembali' => false, 'bm_lawan' => 'bm', 'kirim' => true],
        'bkk' => ['label' => 'BKK – Barang Keluar (Pinjam)',  'aktif' => true,  'kembali' => true,  'bm_lawan' => 'bmk', 'kirim' => true],
        // BKR dibuat otomatis lewat tombol "Kembalikan" di BMK (tidak muncul di pilihan kategori biasa).
        'bkr' => ['label' => 'BKR – Pengembalian Pinjaman',   'aktif' => false, 'kembali' => false, 'bm_lawan' => 'bmr', 'kirim' => true],
        'bkl' => ['label' => 'BKL – Barang Kita di Luar',     'aktif' => true,  'kembali' => true,  'bm_lawan' => null, 'bm_kembali' => 'bml'],
    ],

    'kategori_bm' => [
        'bm'  => 'BM – Barang Masuk',
        'bmk' => 'BMK – Barang Masuk (Pinjam)',
        'bml' => 'BML – Barang Masuk dari Luar (beli / kembali dari BKL)',
        'bmr' => 'BMR – Barang Kembali (Pinjaman)',
    ],

    'prefix_barang_umum' => 'Barang Umum - ',
];