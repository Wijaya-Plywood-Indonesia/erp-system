<?php
// app/Services/Target/Strategies/IndividualTargetStrategy.php
namespace App\Services\Target\Strategies;

class IndividualTargetStrategy implements \App\Contracts\PembagianPotonganStrategyInterface
{
    public function bagikan(array $pekerja, $hasilKolektif, float $ratePerOrgPerMenit, float $biayaPerUnit): array
    {
        return collect($pekerja)->mapWithKeys(function ($p) use ($ratePerOrgPerMenit, $biayaPerUnit) {
            $targetIndividu = $ratePerOrgPerMenit * $p->menitKerja; // target proporsional jam dia sendiri
            $kekuranganIndividu = max(0, $targetIndividu - $p->hasilIndividu);
            $potongan = round(($kekuranganIndividu * $biayaPerUnit) / 500) * 500;

            return [$p->idPegawai => $potongan];
        })->all();
    }
}
