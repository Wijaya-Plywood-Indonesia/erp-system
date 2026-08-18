<?php
// app/DataTransferObjects/TargetHitungResult.php
namespace App\DataTransferObjects;

final class TargetHitungResult
{
    public function __construct(
        public readonly float $targetAdjusted,
        public readonly float $kekurangan,
        public readonly float $potongan,
        public readonly float $potonganPerOrang,
    ) {}
}
