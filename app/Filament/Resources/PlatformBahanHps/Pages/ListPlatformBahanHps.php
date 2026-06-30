<?php

namespace App\Filament\Resources\PlatformBahanHps\Pages;

use App\Filament\Resources\PlatformBahanHps\PlatformBahanHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformBahanHps extends ListRecords
{
    protected static string $resource = PlatformBahanHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
