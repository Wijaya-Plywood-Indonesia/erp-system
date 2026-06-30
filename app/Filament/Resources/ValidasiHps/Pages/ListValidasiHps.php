<?php

namespace App\Filament\Resources\ValidasiHps\Pages;

use App\Filament\Resources\ValidasiHps\ValidasiHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiHps extends ListRecords
{
    protected static string $resource = ValidasiHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
