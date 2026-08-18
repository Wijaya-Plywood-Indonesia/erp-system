<?php
// app/Services/Target/Strategies/KolektifStrategy.php
namespace App\Services\Target\Strategies;

class KolektifStrategy implements \App\Contracts\PembagianPotonganStrategyInterface
{
    public function bagikan(array $pekerja, $hasilKolektif, float $ratePerOrgPerMenit, float $biayaPerUnit): array
    {
        $jumlahOrang = count($pekerja);
        $potonganRata = $jumlahOrang > 0
            ? round(($hasilKolektif->potongan / $jumlahOrang) / 500) * 500
            : 0;

        return collect($pekerja)->mapWithKeys(fn($p) => [$p->idPegawai => $potonganRata])->all();
    }
}
