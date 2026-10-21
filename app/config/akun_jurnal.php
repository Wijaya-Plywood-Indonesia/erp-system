<?php

return [
    // Warna header baris 1 (ARGB). Contoh Oktober: FFFFC6C6
    'warna_header' => 'FFFFC6C6',

    // Isi nama & kode akun menyusul. kode null = dikosongkan.
    'bk' => [
        'debit' => ['nama' => '', 'kode' => null], // persediaan tujuan
        'kredit' => ['nama' => '', 'kode' => null], // persediaan asal
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
