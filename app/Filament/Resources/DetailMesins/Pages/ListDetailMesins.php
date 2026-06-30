<?php

namespace App\Filament\Resources\DetailMesins\Pages;

use App\Filament\Resources\DetailMesins\DetailMesinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailMesins extends ListRecords
{
    protected static string $resource = DetailMesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
