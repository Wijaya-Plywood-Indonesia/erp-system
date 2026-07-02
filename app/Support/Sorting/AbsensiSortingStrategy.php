<?php

namespace App\Support\Sorting;

interface AbsensiSortingStrategy
{
    /**
     * Mengurutkan array data absensi.
     * Setiap strategi WAJIB punya method ini dengan signature yang sama,
     * supaya Absen.php bisa pakai strategi mana saja tanpa peduli isinya.
     */
    public function sort(array $data): array;
}
