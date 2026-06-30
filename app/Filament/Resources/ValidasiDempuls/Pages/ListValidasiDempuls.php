<?php

namespace App\Filament\Resources\ValidasiDempuls\Pages;

use App\Filament\Resources\ValidasiDempuls\ValidasiDempulResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiDempuls extends ListRecords
{
    protected static string $resource = ValidasiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
