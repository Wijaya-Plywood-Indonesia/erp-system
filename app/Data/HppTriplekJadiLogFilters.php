<?php

namespace App\Data;

final readonly class HppTriplekJadiLogFilters
{
    public function __construct(
        public string $jenisKayu = '',
        public string $panjang = '',
        public string $tebal = '',
        public string $kw = '',
        public string $tanggalMulai = '',
        public string $tanggalSelesai = '',
    ) {}

    public function hasActive(): bool
    {
        return array_filter(
            get_object_vars($this),
            fn(string $value): bool => trim($value) !== ''
        ) !== [];
    }
}
