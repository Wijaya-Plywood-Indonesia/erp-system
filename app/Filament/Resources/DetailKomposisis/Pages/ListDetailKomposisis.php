<?php

namespace App\Filament\Resources\DetailKomposisis\Pages;

use App\Filament\Resources\DetailKomposisis\DetailKomposisiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailKomposisis extends ListRecords
{
    protected static string $resource = DetailKomposisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
