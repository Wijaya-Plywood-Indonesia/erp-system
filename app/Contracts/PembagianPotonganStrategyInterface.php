<?php
// app/Contracts/PembagianPotonganStrategyInterface.php
namespace App\Contracts;

interface PembagianPotonganStrategyInterface
{
    /**
     * @param \App\DataTransferObjects\PekerjaKerjaInput[] $pekerja
     * @return array{targetAdjusted: float, potonganPerPegawai: array<string, float>}
     */
    public function bagikan(
        array $pekerja,
        float $ratePerOrgPerMenit,
        float $biayaPerUnit,
        float $hasilAktual,
        float $gaji,
    ): array;
}
