<?php

namespace App\Filament\Resources\Neracas\Pages;

use App\Filament\Resources\Neracas\NeracaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNeracas extends ListRecords
{
    protected static string $resource = NeracaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
