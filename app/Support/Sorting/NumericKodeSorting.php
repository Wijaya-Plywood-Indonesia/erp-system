<?php

namespace App\Support\Sorting;

class NumericKodeSorting implements AbsensiSortingStrategy
{
    public function sort(array $data): array
    {
        usort($data, function ($a, $b) {
            // Pastikan kode diubah ke integer agar urutan numeriknya benar
            // Contoh: '2' akan muncul sebelum '10' (kalau string, '10' muncul duluan)
            $kodeA = (int) ($a['kodep'] ?? 0);
            $kodeB = (int) ($b['kodep'] ?? 0);

            return $kodeA <=> $kodeB;
        });

        return array_values($data);
    }
}
