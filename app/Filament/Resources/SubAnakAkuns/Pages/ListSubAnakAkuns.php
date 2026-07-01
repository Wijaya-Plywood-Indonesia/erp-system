<?php

namespace App\Filament\Resources\SubAnakAkuns\Pages;

use App\Filament\Resources\SubAnakAkuns\SubAnakAkunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubAnakAkuns extends ListRecords
{
    protected static string $resource = SubAnakAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
