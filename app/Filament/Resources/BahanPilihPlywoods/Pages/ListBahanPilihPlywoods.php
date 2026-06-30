<?php

namespace App\Filament\Resources\BahanPilihPlywoods\Pages;

use App\Filament\Resources\BahanPilihPlywoods\BahanPilihPlywoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanPilihPlywoods extends ListRecords
{
    protected static string $resource = BahanPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
