<?php
// app/Services/Target/Resolvers/UkuranBasedResolver.php
namespace App\Services\Target\Resolvers;

use App\Contracts\TargetResolverInterface;
use App\Models\Target;

class UkuranBasedResolver implements TargetResolverInterface
{
    public function resolve(
        int $idMesin,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
        ?string $grade = null,
    ): ?Target {
        return Target::query()
            ->where('id_mesin', $idMesin)
            ->when($idUkuran, fn($q) => $q->where('id_ukuran', $idUkuran))
            ->when($idJenisKayu, fn($q) => $q->where('id_jenis_kayu', $idJenisKayu))
            // Satu kombinasi ukuran + jenis kayu bisa punya beberapa baris target
            // yang cuma beda di `grade` (KW). Tanpa filter ini, orderByDesc('id')
            // bisa mengambil baris KW yang salah (asal paling baru diinput).
            ->when($grade, fn($q) => $q->where('grade', $grade))
            ->orderByDesc('id')
            ->first();
    }
}
