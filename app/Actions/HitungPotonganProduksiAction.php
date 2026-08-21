<?php

// app/Actions/HitungPotonganProduksiAction.php

namespace App\Actions;

use App\DataTransferObjects\PekerjaKerjaInput;
use App\DataTransferObjects\TargetHitungInput;
use App\DataTransferObjects\TargetHitungResult;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Models\Target;
use App\Services\Target\TargetPotonganService;
use App\Services\Target\TargetResolverFactory;

class HitungPotonganProduksiAction
{
    public function __construct(
        private readonly TargetPotonganService $service = new TargetPotonganService,
    ) {}

    /**
     * @param  PekerjaKerjaInput[]  $pekerja  durasi kerja aktual (menit) & (opsional) hasil individu tiap pegawai
     */
    public function execute(
        Mesin $mesin,
        StrategiPembagian $strategi,
        array $pekerja,
        float $hasilAktual,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
        ?Target $targetOverride = null,
    ): ?TargetHitungResult {
        $target = $targetOverride ?? $this->resolveTarget($mesin, $idUkuran, $idJenisKayu);

        if (! $target) {
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

    /**
     * Alur khusus Join: cuma ambil Target mentah + rate per-orang-per-menit,
     * TANPA langsung dibagi ke pegawai. JoinDataMap pakai ini per ukuran
     * untuk hitung targetAdjusted tiap ukuran, gabungkan (netting) dulu
     * lintas ukuran, baru tentukan potongan kolektif final & bagi (misal
     * pakai ProporsionalStrategy) sekali di akhir — bukan per ukuran.
     *
     * @return array{target: Target, ratePerOrgPerMenit: float}|null
     */
    public function resolveTargetDanRate(Mesin $mesin, ?int $idUkuran = null, ?int $idJenisKayu = null): ?array
    {
        $target = $this->resolveTarget($mesin, $idUkuran, $idJenisKayu);

        if (! $target) {
            return null;
        }

        $menitNormalTotal = ((float) $target->jam) * 60;
        $orgNormal = (int) $target->orang;

        $ratePerMenit = ($orgNormal > 0 && $menitNormalTotal > 0)
            ? ((float) $target->target) / $menitNormalTotal
            : 0.0;

        $ratePerOrgPerMenit = $orgNormal > 0 ? $ratePerMenit / $orgNormal : 0.0;

        return [
            'target' => $target,
            'ratePerOrgPerMenit' => $ratePerOrgPerMenit,
        ];
    }

    private function resolveTarget(Mesin $mesin, ?int $idUkuran, ?int $idJenisKayu): ?Target
    {
        $resolver = TargetResolverFactory::make($mesin);

        return $resolver->resolve($mesin->value, $idUkuran, $idJenisKayu);
    }
}
