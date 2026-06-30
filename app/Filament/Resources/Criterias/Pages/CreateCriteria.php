<?php

namespace App\Filament\Resources\Criterias\Pages;

use App\Filament\Resources\Criterias\CriteriaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCriteria extends CreateRecord
{
    protected static string $resource = CriteriaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
