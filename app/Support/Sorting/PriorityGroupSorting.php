<?php

namespace App\Support\Sorting;

class PriorityGroupSorting implements AbsensiSortingStrategy
{
    public function sort(array $data): array
    {
        usort($data, function ($a, $b) {
            $kodeA = trim((string) ($a['kodep'] ?? ''));
            $kodeB = trim((string) ($b['kodep'] ?? ''));

            $prioA = $this->getPriority($kodeA);
            $prioB = $this->getPriority($kodeB);

            if ($prioA !== $prioB) {
                return $prioA <=> $prioB;
            }

            return (int) $kodeA <=> (int) $kodeB;
        });

        return array_values($data);
    }

    private function getPriority(string $kode): int
    {
        // Prioritas 1: Kode berawalan angka 8 atau 9 (Paling atas)
        if (str_starts_with($kode, '8') || str_starts_with($kode, '9')) {
            return 1;
        }

        // Prioritas 3: Kode berawalan angka 7 (Paling bawah)
        if (str_starts_with($kode, '7')) {
            return 3;
        }

        // Prioritas 2: Kode berawalan lainnya (1-6, dll.) diletakkan di tengah
        return 2;
    }
}
