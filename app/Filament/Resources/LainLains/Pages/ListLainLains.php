<?php

namespace App\Filament\Resources\LainLains\Pages;

use App\Filament\Resources\LainLains\LainLainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLainLains extends ListRecords
{
    protected static string $resource = LainLainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
