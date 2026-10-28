<?php

return [
    // Warna header baris 1 (ARGB). Contoh Oktober: FFFFC6C6
    'warna_header' => 'FFFFC6C6',

    /*
     * Veneer (khusus bk): kunci "tipe|kelompok|kayu".
     * Kode = kode dasar akun WJY (kredit, x.00); akun WHN (debit) otomatis kode + 0.01,
     * nama diberi akhiran " WHN" / " WJY". Sesuai COA.
     * Harga TIDAK di sini: diambil dari ReferensiHargaProduksi::findReferensi().
     */
    'veneer' => [
        // Basah
        'basah|260|sengon' => ['nama' => 'Veneer Basah 260 face/back sengon', 'kode' => 1421],
        'basah|260|meranti' => ['nama' => 'Veneer Basah 260 face/back meranti', 'kode' => 1422],
        'basah|130|sengon' => ['nama' => 'Veneer Basah 130 core sengon', 'kode' => 1426],
        'basah|130|meranti' => ['nama' => 'Veneer Basah 130 core meranti', 'kode' => 1427],
        'basah|ppc|meranti' => ['nama' => 'Veneer Basah ppc meranti',     'kode' => 1428],
        'basah|ppc|sengon' => ['nama' => 'Veneer Basah ppc sengon',      'kode' => 1429],

        // Jadi
        'jadi|260|sengon' => ['nama' => 'Veneer Jadi 260 face/back sengon',  'kode' => 1461],
        'jadi|260|meranti' => ['nama' => 'Veneer Jadi 260 face/back meranti', 'kode' => 1462],
        'jadi|130|sengon' => ['nama' => 'Veneer Jadi 130 core sengon',       'kode' => 1466],
        'jadi|130|meranti' => ['nama' => 'Veneer Jadi 130 core meranti',      'kode' => 1467],
        'jadi|ppc|meranti' => ['nama' => 'Veneer Jadi ppc meranti',           'kode' => 1471],
        'jadi|ppc|sengon' => ['nama' => 'Veneer Jadi ppc sengon',            'kode' => 1472],

        // Kering
        'kering|260|sengon' => ['nama' => 'Veneer Kering 260 face/back sengon',  'kode' => 1441],
        'kering|260|meranti' => ['nama' => 'Veneer Kering 260 face/back meranti', 'kode' => 1442],
        'kering|130|sengon' => ['nama' => 'Veneer Kering 130 core sengon',       'kode' => 1446],
        'kering|130|meranti' => ['nama' => 'Veneer Kering 130 core meranti',      'kode' => 1447],
        'kering|ppc|meranti' => ['nama' => 'Veneer Kering ppc meranti',           'kode' => 1451],
        'kering|ppc|sengon' => ['nama' => 'Veneer Kering ppc sengon',            'kode' => 1452],
    ],

    /*
     * Pencocokan jenis kayu -> kunci akun veneer. nama_kayu dicocokkan kalau MENGANDUNG
     * kunci (mis. "Sengon Super"), kode_kayu dicocokkan PERSIS dengan salah satu isi daftar.
     * Sengon eksklusif (hanya sengon). Waru dianggap meranti.
     */
    'kayu' => [
        'sengon' => ['sengon', 'sgn', 's'],
        'meranti' => ['meranti', 'waru', 'mrt', 'm'],
    ],

    'bk' => [
        // Kolom "Nama" di jurnal bk 2026 selalu 'gudang setran'. null = pakai tujuan_nota.
        'nama' => 'gudang setran',

        // Fallback untuk barang non-veneer yang belum ada di 'barang'. Isi menyusul.
        'debit' => ['nama' => '', 'kode' => null], // persediaan tujuan
        'kredit' => ['nama' => '', 'kode' => null], // persediaan asal

        // Barang non-veneer: kunci = nama_barang huruf kecil (persis seperti di nota).
        // Contoh dari data bk 2026; lengkapi sesuai master barang.
        'barang' => [
            'isi staples' => [
                'debit' => ['nama' => 'Isi Staples', 'kode' => 1507.13],
                'kredit' => ['nama' => 'Isi Staples WJY', 'kode' => 1507.61],
            ],
            'staples' => [
                'debit' => ['nama' => 'Isi Staples', 'kode' => 1507.13],
                'kredit' => ['nama' => 'Isi Staples WJY', 'kode' => 1507.61],
            ],
            'isolasi coklat' => [
                'debit' => ['nama' => 'isolasi coklat WHN', 'kode' => 1507.35],
                'kredit' => ['nama' => 'isolasi coklat WJY', 'kode' => 1507.67],
            ],
            'kayu bakar' => [
                'debit' => ['nama' => 'kayu bakar', 'kode' => 4109],
                'kredit' => ['nama' => 'kayu bakar', 'kode' => 4109],
            ],
            // Dicocokkan dengan jurnal bk 2026: stapler 6 pcs untuk hotpress wahana.
            // Kalau yang dimaksud isi staples, ganti akunnya ke Isi Staples (1507.13 / 1507.61).
            'straples joyko' => [
                'debit' => ['nama' => 'Beban Oprasional Produksi', 'kode' => 5402],
                'kredit' => ['nama' => 'stapler', 'kode' => 1507.37],
            ],
            'peralatan' => [
                'debit' => ['nama' => 'Peralatan', 'kode' => 1700],
                'kredit' => ['nama' => 'Peralatan', 'kode' => 1700],
            ],
        ],
    ],
    'bkl' => [
        'debit' => ['nama' => '', 'kode' => null], // piutang / beban
        'kredit' => ['nama' => '', 'kode' => null], // persediaan
    ],
    'bm' => [
        'debit' => ['nama' => '', 'kode' => null], // beban / persediaan
        'kredit' => ['nama' => '', 'kode' => null], // kas / hutang
    ],
    'bml' => [
        'debit' => ['nama' => '', 'kode' => null], // beban lain
        'kredit' => ['nama' => '', 'kode' => null], // kas / hutang
    ],

    // id_rekening_perusahaan => ['nama' => 'BANK INDUSTRI', 'kode' => 1202]
    'bank' => [
        // 1 => ['nama' => 'BANK 999', 'kode' => 1201],
    ],
];
