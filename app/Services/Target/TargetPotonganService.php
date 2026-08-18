<?php
// app/Services/Target/TargetPotonganService.php
namespace App\Services\Target;

use App\DataTransferObjects\TargetHitungInput;
use App\DataTransferObjects\TargetHitungResult;

class TargetPotonganService
{
    public function hitung(TargetHitungInput $in): TargetHitungResult
    {
        $menitNormalTotal = $in->jamNormal * 60;
        $menitAktualTotal = ($in->jamAktual * 60) + $in->menitAktual;

        $ratePerMenit       = $in->orgNormal > 0 && $menitNormalTotal > 0
            ? $in->targetNormal / $menitNormalTotal
            : 0;
        $ratePerOrgPerMenit = $in->orgNormal > 0 ? $ratePerMenit / $in->orgNormal : 0;

        $targetAdjusted = $ratePerOrgPerMenit * $in->orgAktual * $menitAktualTotal;

        $kekurangan = max(0, $targetAdjusted - $in->hasilAktual);
        $potongan   = $kekurangan * $in->biayaPerUnit;

        $potonganPerOrang = $in->orgAktual > 0
            ? round(($potongan / $in->orgAktual) / 500) * 500
            : 0;

        return new TargetHitungResult(
            targetAdjusted: $targetAdjusted,
            kekurangan: $kekurangan,
            potongan: $potongan,
            potonganPerOrang: $potonganPerOrang,
        );
    }
}
