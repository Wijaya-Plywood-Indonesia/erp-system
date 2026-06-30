<?php

namespace App\Filament\Resources\DetailLainLains\Pages;

use App\Filament\Resources\DetailLainLains\DetailLainLainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailLainLains extends ListRecords
{
    protected static string $resource = DetailLainLainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
