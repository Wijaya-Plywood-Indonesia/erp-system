<?php
// app/Contracts/PembagianPotonganStrategyInterface.php
namespace App\Contracts;

use App\DataTransferObjects\TargetHitungResult;

interface PembagianPotonganStrategyInterface
{
    /**
     * @param PekerjaKerjaInput[] $pekerja
     * @return array<string, float> idPegawai => potonganPerOrang
     */
    public function bagikan(array $pekerja, TargetHitungResult $hasilKolektif, float $ratePerOrgPerMenit, float $biayaPerUnit): array;
}
