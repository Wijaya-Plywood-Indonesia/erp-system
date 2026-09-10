<?php
// app/Services/Target/TargetResolverFactory.php
namespace App\Services\Target;

use App\Enums\Mesin;
use App\Contracts\TargetResolverInterface;
use App\Services\Target\Resolvers\ShiftBasedResolver;
use App\Services\Target\Resolvers\UkuranBasedResolver;

class TargetResolverFactory
{
    public static function make(Mesin $mesin): TargetResolverInterface
    {
        return $mesin->resolveByShiftOnly()
            ? new ShiftBasedResolver()
            : new UkuranBasedResolver();
    }
}
