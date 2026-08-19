<?php
// app/Services/Target/Strategies/ProporsionalStrategy.php
namespace App\Services\Target\Strategies;

/**
 * BUKAN implementasi PembagianPotonganStrategyInterface — strategi ini
 * levelnya beda: dia tidak menghitung target/potongan dari rate, tapi
 * MEMBAGI satu angka potongan kolektif (yang sudah dihitung di tempat lain,
 * misal hasil netting lintas-ukuran untuk Join) ke tiap pekerja SESUAI
 * PORSI JAM KERJANYA (bukan rata seperti KolektifStrategy).
 *
 * Dipakai sebagai tahap terakhir setelah potongan kolektif final didapat,
 * bukan dipilih lewat StrategiPembagianFactory.
 */
class ProporsionalStrategy
{
    /**
     * @param \App\DataTransferObjects\PekerjaKerjaInput[] $pekerja
     * @return array<string, float> idPegawai => nilai potongan (Rp, dibulatkan kelipatan 500)
     */
    public function bagikan(array $pekerja, float $totalPotongan): array
    {
        $totalMenit = array_sum(array_map(fn($p) => $p->menitKerja, $pekerja));

        if ($totalMenit <= 0 || $totalPotongan <= 0) {
            return collect($pekerja)->mapWithKeys(fn($p) => [$p->idPegawai => 0.0])->all();
        }

        return collect($pekerja)->mapWithKeys(function ($p) use ($totalPotongan, $totalMenit) {
            $porsi = $p->menitKerja / $totalMenit;
            $nilai = round(($totalPotongan * $porsi) / 500) * 500;
            return [$p->idPegawai => $nilai];
        })->all();
    }
}