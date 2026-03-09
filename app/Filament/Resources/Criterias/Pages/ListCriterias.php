<?php

namespace App\Filament\Resources\Criterias\Pages;

use App\Filament\Resources\Criterias\CriteriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCriterias extends ListRecords
{
    protected static string $resource = CriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
