<?php

namespace App\Filament\Resources\PegawaiPotSikus\Pages;

use App\Filament\Resources\PegawaiPotSikus\PegawaiPotSikuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiPotSikus extends ListRecords
{
    protected static string $resource = PegawaiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
