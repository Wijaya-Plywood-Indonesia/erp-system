<?php
// app/DataTransferObjects/TargetHitungInput.php
namespace App\DataTransferObjects;

final class TargetHitungInput
{
    public function __construct(
        public readonly float $targetNormal,   // target
        public readonly int $orgNormal,        // orang
        public readonly float $jamNormal,      // jam
        public readonly int $orgAktual,        // T
        public readonly float $jamAktual,      // R
        public readonly float $menitAktual,    // S
        public readonly float $hasilAktual,    // U
        public readonly float $biayaPerUnit,   // potongan (generated column)
    ) {}
}
