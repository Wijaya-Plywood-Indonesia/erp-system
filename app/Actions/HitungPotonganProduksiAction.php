<?php
// app/Actions/HitungPotonganProduksiAction.php
namespace App\Actions;

use App\Enums\Mesin;
use App\Services\Target\TargetResolverFactory;
use App\Services\Target\StrategiPembagianFactory;

class HitungPotonganProduksiAction
{
    /**
     * @param \App\DataTransferObjects\PekerjaKerjaInput[] $pekerja
     * @return array{targetAdjusted: float, potonganPerPegawai: array<string, float>}
     */
    public function execute(
        Mesin $mesin,
        array $pekerja,
        float $hasilAktual = 0,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
    ): array {
        $resolver = TargetResolverFactory::make($mesin);
        $target   = $resolver->resolve($mesin->value, $idUkuran, $idJenisKayu);

        if (!$target) {
            return ['targetAdjusted' => 0, 'potonganPerPegawai' => []];
        }

        $menitNormalTotal   = ((float) $target->jam) * 60;
        $ratePerMenit       = $target->orang > 0 && $menitNormalTotal > 0
            ? $target->target / $menitNormalTotal : 0;
        $ratePerOrgPerMenit = $target->orang > 0 ? $ratePerMenit / $target->orang : 0;

        $strategi = StrategiPembagianFactory::make($mesin->strategiPembagian());

        return $strategi->bagikan(
            pekerja: $pekerja,
            ratePerOrgPerMenit: $ratePerOrgPerMenit,
            biayaPerUnit: (float) $target->potongan,
            hasilAktual: $hasilAktual,
            gaji: (float) $target->gaji,
        );
    }
}
