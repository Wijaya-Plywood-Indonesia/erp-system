<?php
// app/Services/Target/Strategies/IndividualTargetStrategy.php
namespace App\Services\Target\Strategies;

use App\Contracts\PembagianPotonganStrategyInterface;

class IndividualTargetStrategy implements PembagianPotonganStrategyInterface
{
    public function bagikan(array $pekerja, float $ratePerOrgPerMenit, float $biayaPerUnit, float $hasilAktual, float $gaji): array
    {
        $targetTotal = 0;
        $map = [];

        foreach ($pekerja as $p) {
            $targetIndividu = $ratePerOrgPerMenit * $p->menitKerja;
            $targetTotal   += $targetIndividu;

            $kekuranganIndividu = max(0, $targetIndividu - $p->hasilIndividu);
            $map[$p->idPegawai] = round(($kekuranganIndividu * $biayaPerUnit) / 500) * 500;
        }

        return ['targetAdjusted' => $targetTotal, 'potonganPerPegawai' => $map];
    }
}
