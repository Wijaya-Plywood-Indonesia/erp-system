<?php

namespace App\Filament\Resources\AnakAkuns\Pages;

use App\Filament\Resources\AnakAkuns\AnakAkunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnakAkuns extends ListRecords
{
    protected static string $resource = AnakAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
