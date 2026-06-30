<?php

namespace App\Filament\Resources\PlatformHasilHps\Pages;

use App\Filament\Resources\PlatformHasilHps\PlatformHasilHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformHasilHps extends ListRecords
{
    protected static string $resource = PlatformHasilHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
