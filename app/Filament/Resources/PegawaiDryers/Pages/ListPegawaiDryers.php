<?php

namespace App\Filament\Resources\PegawaiDryers\Pages;

use App\Filament\Resources\PegawaiDryers\PegawaiDryerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiDryers extends ListRecords
{
    protected static string $resource = PegawaiDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
