<?php
// app/Services/Target/Strategies/KolektifStrategy.php
namespace App\Services\Target\Strategies;

use App\Contracts\PembagianPotonganStrategyInterface;

class KolektifStrategy implements PembagianPotonganStrategyInterface
{
    public function bagikan(array $pekerja, float $ratePerOrgPerMenit, float $biayaPerUnit, float $hasilAktual, float $gaji): array
    {
        $totalMenit = array_sum(array_map(fn($p) => $p->menitKerja, $pekerja));
        $orgAktual  = count($pekerja);
        $targetAdjusted = $ratePerOrgPerMenit * $totalMenit;

        if ($hasilAktual <= 0) {
            // Hasil nol: tiap orang kena potongan penuh sebesar gaji, TIDAK dibagi
            $potonganPerOrang = $gaji;
        } else {
            $kekurangan = max(0, $targetAdjusted - $hasilAktual);
            $potongan   = $kekurangan * $biayaPerUnit;
            $potonganPerOrang = $orgAktual > 0
                ? round(($potongan / $orgAktual) / 500) * 500 : 0;
        }

        $map = collect($pekerja)->mapWithKeys(fn($p) => [$p->idPegawai => $potonganPerOrang])->all();

        return ['targetAdjusted' => $targetAdjusted, 'potonganPerPegawai' => $map];
    }
}
