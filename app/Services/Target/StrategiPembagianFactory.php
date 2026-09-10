<?php

namespace App\Services\Target;

use App\Enums\StrategiPembagian;
use App\Contracts\PembagianPotonganStrategyInterface;
use App\Services\Target\Strategies\{KolektifStrategy, IndividualTargetStrategy};

class StrategiPembagianFactory
{
    public static function make(StrategiPembagian $strategi): PembagianPotonganStrategyInterface
    {
        return match ($strategi) {
            StrategiPembagian::Kolektif => new KolektifStrategy(),
            StrategiPembagian::IndividualTarget => new IndividualTargetStrategy(),
        };
    }
}
