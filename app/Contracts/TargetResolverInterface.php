<?php
// app/Contracts/TargetResolverInterface.php
namespace App\Contracts;

use App\Models\Target;

interface TargetResolverInterface
{
    /**
     * @param string|null $grade  KW/grade barang (kolom `grade`, varchar di tabel targets).
     *                            Opsional — resolver yang tidak butuh grade (mis. ShiftBasedResolver)
     *                            boleh mengabaikan parameter ini.
     */
    public function resolve(
        int $idMesin,
        ?int $idUkuran = null,
        ?int $idJenisKayu = null,
        ?string $grade = null,
    ): ?Target;
}
