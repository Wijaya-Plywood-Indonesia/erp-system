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
        $ratePerMenit = $in->orgNormal > 0 && $menitNormalTotal > 0
            ? $in->targetNormal / $menitNormalTotal : 0;
        $ratePerOrgPerMenit = $in->orgNormal > 0 ? $ratePerMenit / $in->orgNormal : 0;

        $totalMenitAktual = array_sum(array_map(fn($p) => $p->menitKerja, $in->pekerja));
        $orgAktual = count($in->pekerja);

        $targetAdjusted = $ratePerOrgPerMenit * $totalMenitAktual;
        $kekurangan     = max(0, $targetAdjusted - $in->hasilAktual);

        // Hasil nol: tiap orang kena potongan penuh sebesar gaji harian masing-masing, tanpa dibagi
        if ($in->hasilAktual <= 0) {
            $potongan         = $in->gaji * $orgAktual; // total, cuma untuk pelaporan/summary
            $potonganPerOrang = $in->gaji;                // langsung gaji per orang, TIDAK dibagi
        } else {
            $potongan         = $kekurangan * $in->biayaPerUnit;
            $potonganPerOrang = $orgAktual > 0
                ? round(($potongan / $orgAktual) / 500) * 500 : 0;
        }

        return new TargetHitungResult($targetAdjusted, $kekurangan, $potongan, $potonganPerOrang);
    }
}
