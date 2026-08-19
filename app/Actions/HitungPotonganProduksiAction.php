<?php
// app/Actions/HitungPotonganProduksiAction.php
namespace App\Actions;

use App\DataTransferObjects\TargetHitungInput;
use App\DataTransferObjects\TargetHitungResult;
use App\Enums\Mesin;
use App\Services\Target\TargetPotonganService;
use App\Services\Target\TargetResolverFactory;

class HitungPotonganProduksiAction
{
    public function __construct(
        private readonly TargetPotonganService $service = new TargetPotonganService(),
    ) {}

    /**
     * @param \App\DataTransferObjects\PekerjaKerjaInput[] $pekerja
     */
    public function execute(
        Mesin $mesin,
        array $pekerja,
        float $hasilAktual,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
    ): ?TargetHitungResult {
        $resolver = TargetResolverFactory::make($mesin);
        $target   = $resolver->resolve($mesin->value, $idUkuran, $idJenisKayu);

        if (!$target) {
            return null;
        }

        $input = new TargetHitungInput(
            targetNormal: (float) $target->target,
            orgNormal: (int) $target->orang,
            jamNormal: (float) $target->jam,
            pekerja: $pekerja,
            hasilAktual: $hasilAktual,
            biayaPerUnit: (float) $target->potongan,
            gaji: (float) $target->gaji,
        );

        return $this->service->hitung($input);
    }
}
