<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Pages;

use App\Filament\Resources\OngkosProduksiDryers\OngkosProduksiDryerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOngkosProduksiDryers extends ListRecords
{
    protected static string $resource = OngkosProduksiDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
