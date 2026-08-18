<?php
// app/Services/Target/StrategiPembagianFactory.php
namespace App\Services\Target;

use App\Enums\StrategiPembagian;
use App\Services\Target\Strategies\{KolektifStrategy, ProporsionalStrategy, IndividualTargetStrategy};

class StrategiPembagianFactory
{
    public static function make(StrategiPembagian $strategi): \App\Contracts\PembagianPotonganStrategyInterface
    {
        return match ($strategi) {
            StrategiPembagian::Kolektif => new KolektifStrategy(),
            StrategiPembagian::Proporsional => new ProporsionalStrategy(),
            StrategiPembagian::IndividualTarget => new IndividualTargetStrategy(),
        };
    }
}
