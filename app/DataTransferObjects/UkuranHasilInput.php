<?php

namespace App\DataTransferObjects;

final class UkuranHasilInput
{
    public function __construct(
        public readonly string $kodeUkuran,
        public readonly float $targetHarian,
        public readonly float $hasilAktual,
        public readonly float $biayaPerUnit,
    ) {}
}
