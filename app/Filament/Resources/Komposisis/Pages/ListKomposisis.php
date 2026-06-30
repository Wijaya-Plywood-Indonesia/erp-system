<?php

namespace App\Filament\Resources\Komposisis\Pages;

use App\Filament\Resources\Komposisis\KomposisiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKomposisis extends ListRecords
{
    protected static string $resource = KomposisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
