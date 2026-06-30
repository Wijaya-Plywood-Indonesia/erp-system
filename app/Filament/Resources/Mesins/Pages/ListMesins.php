<?php

namespace App\Filament\Resources\Mesins\Pages;

use App\Filament\Resources\Mesins\MesinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMesins extends ListRecords
{
    protected static string $resource = MesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
