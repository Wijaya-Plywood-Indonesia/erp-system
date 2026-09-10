<?php

namespace App\Services\FingerParsers;

use Illuminate\Support\Collection;

interface FingerParserInterface
{
    /**
     * Cek apakah parser ini cocok untuk file dengan baris pertama seperti ini.
     * Dipakai untuk auto-detect format, tanpa perlu user pilih manual.
     */
    public function supports(string $firstLine): bool;

    /**
     * Parse isi file menjadi koleksi tap mentah yang seragam.
     * Setiap item: ['kode_pegawai' => string, 'waktu' => \Illuminate\Support\Carbon]
     */
    public function parse(string $absolutePath): Collection;

    /**
     * Nama format, buat keperluan log/debug.
     */
    public function label(): string;
}
