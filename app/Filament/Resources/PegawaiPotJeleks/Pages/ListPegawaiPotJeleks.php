<?php

namespace App\Filament\Resources\PegawaiPotJeleks\Pages;

use App\Filament\Resources\PegawaiPotJeleks\PegawaiPotJelekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiPotJeleks extends ListRecords
{
    protected static string $resource = PegawaiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
