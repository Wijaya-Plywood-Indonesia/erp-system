<?php
// app/Services/Target/Resolvers/UkuranBasedResolver.php
namespace App\Services\Target\Resolvers;

use App\Contracts\TargetResolverInterface;
use App\Models\Target;

class UkuranBasedResolver implements TargetResolverInterface
{
    public function resolve(int $idMesin, ?int $idUkuran = null, ?int $idJenisKayu = null): ?Target
    {
        return Target::query()
            ->where('id_mesin', $idMesin)
            ->when($idUkuran, fn($q) => $q->where('id_ukuran', $idUkuran))
            ->when($idJenisKayu, fn($q) => $q->where('id_jenis_kayu', $idJenisKayu))
            ->orderByDesc('id')
            ->first();
    }
}
