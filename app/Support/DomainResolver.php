<?php

namespace App\Support;

use App\Support\Sorting\AbsensiSortingStrategy;
use Illuminate\Support\Facades\Request;

class DomainResolver
{
    public function sortingStrategy(): AbsensiSortingStrategy
    {
        $host = Request::getHost();

        $strategyClass = config("tenants.domains.{$host}.sorting_strategy")
            ?? config('tenants.default_sorting_strategy');

        return app($strategyClass);
    }
}
