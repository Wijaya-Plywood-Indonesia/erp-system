<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Pages;

use App\Filament\Resources\HasilPilihPlywoods\HasilPilihPlywoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilPilihPlywoods extends ListRecords
{
    protected static string $resource = HasilPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
