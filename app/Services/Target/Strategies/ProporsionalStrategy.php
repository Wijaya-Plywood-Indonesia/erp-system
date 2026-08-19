<?php
// app/Services/Target/Strategies/ProporsionalStrategy.php
namespace App\Services\Target\Strategies;

class ProporsionalStrategy implements \App\Contracts\PembagianPotonganStrategyInterface
{
    public function bagikan(array $pekerja, $hasilKolektif, float $ratePerOrgPerMenit, float $biayaPerUnit): array
    {
        $totalMenit = array_sum(array_map(fn($p) => $p->menitKerja, $pekerja));
        if ($totalMenit <= 0) {
            return collect($pekerja)->mapWithKeys(fn($p) => [$p->idPegawai => 0.0])->all();
        }

        return collect($pekerja)->mapWithKeys(function ($p) use ($hasilKolektif, $totalMenit) {
            $porsi = $p->menitKerja / $totalMenit;
            $nilai = round(($hasilKolektif->potongan * $porsi) / 500) * 500;
            return [$p->idPegawai => $nilai];
        })->all();
    }
}
