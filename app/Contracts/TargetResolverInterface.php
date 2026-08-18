<?php
// app/Contracts/TargetResolverInterface.php
namespace App\Contracts;

use App\Models\Target;

interface TargetResolverInterface
{
    public function resolve(int $idMesin, ?int $idUkuran = null, ?int $idJenisKayu = null): ?Target;
}
