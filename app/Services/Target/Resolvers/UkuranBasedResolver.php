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

        if (!$target && $idUkuran) {
            $ukuranNol = \App\Models\Ukuran::where('panjang', 0)
                ->where('lebar', 0)
                ->where('tebal', 0)
                ->first();

            if ($ukuranNol && $ukuranNol->id !== $idUkuran) {
                $target = Target::query()
                    ->where('id_mesin', $idMesin)
                    ->where('id_ukuran', $ukuranNol->id)
                    ->when($idJenisKayu, fn($q) => $q->where('id_jenis_kayu', $idJenisKayu))
                    ->orderByDesc('id')
                    ->first();
            }
        }

        return $target;
    }
}
