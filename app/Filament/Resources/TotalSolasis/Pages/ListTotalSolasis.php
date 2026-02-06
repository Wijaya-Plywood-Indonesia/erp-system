<?php

namespace App\Filament\Resources\TotalSolasis\Pages;

use App\Filament\Resources\TotalSolasis\TotalSolasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTotalSolasis extends ListRecords
{
    protected static string $resource = TotalSolasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
