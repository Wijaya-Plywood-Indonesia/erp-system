<?php
// app/Actions/HitungPotonganProduksiAction.php
namespace App\Actions;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\DataTransferObjects\TargetHitungInput;
use App\DataTransferObjects\TargetHitungResult;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Services\Target\TargetPotonganService;
use App\Services\Target\TargetResolverFactory;

class HitungPotonganProduksiAction
{
    public function __construct(
        private readonly TargetPotonganService $service = new TargetPotonganService(),
    ) {}

    /**
     * @param PekerjaKerjaInput[] $pekerja  durasi kerja aktual (menit) & (opsional) hasil individu tiap pegawai
     */
    public function execute(
        Mesin $mesin,
        StrategiPembagian $strategi,
        array $pekerja,
        float $hasilAktual,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
        ?\App\Models\Target $targetOverride = null,
    ): ?TargetHitungResult {
        $resolver = TargetResolverFactory::make($mesin);
        $target   = $targetOverride ?? $resolver->resolve($mesin->value, $idUkuran, $idJenisKayu);

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
            gaji: (float) ($target->gaji ?? 0),
        );

        return $this->service->hitung($input, $strategi);
    }
}