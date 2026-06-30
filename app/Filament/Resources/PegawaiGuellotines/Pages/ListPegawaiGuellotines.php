<?php

namespace App\Filament\Resources\PegawaiGuellotines\Pages;

use App\Filament\Resources\PegawaiGuellotines\PegawaiGuellotineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiGuellotines extends ListRecords
{
    protected static string $resource = PegawaiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
