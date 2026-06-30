<?php

namespace App\Filament\Resources\DetailMasuks\Pages;

use App\Filament\Resources\DetailMasuks\DetailMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailMasuks extends ListRecords
{
    protected static string $resource = DetailMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
