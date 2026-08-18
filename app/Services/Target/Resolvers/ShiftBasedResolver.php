<?php
// app/Services/Target/Resolvers/ShiftBasedResolver.php
namespace App\Services\Target\Resolvers;

use App\Contracts\TargetResolverInterface;
use App\Models\Target;

class ShiftBasedResolver implements TargetResolverInterface
{
    public function resolve(int $idMesin, ?int $idUkuran = null, ?int $idJenisKayu = null): ?Target
    {
        return Target::query()
            ->where('id_mesin', $idMesin)
            ->orderByDesc('id')
            ->first();
    }
}
