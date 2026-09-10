<?php
// app/Services/Target/Resolvers/ShiftBasedResolver.php
namespace App\Services\Target\Resolvers;

use App\Contracts\TargetResolverInterface;
use App\Models\Target;

class ShiftBasedResolver implements TargetResolverInterface
{
    public function resolve(
        int $idMesin,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
        ?string $grade = null,
    ): ?Target {
        // Mesin berbasis shift tidak dibedakan per ukuran/jenis kayu/grade,
        // jadi parameter tambahan di atas sengaja tidak dipakai di sini.
        return Target::query()
            ->where('id_mesin', $idMesin)
            ->orderByDesc('id')
            ->first();
    }
}
