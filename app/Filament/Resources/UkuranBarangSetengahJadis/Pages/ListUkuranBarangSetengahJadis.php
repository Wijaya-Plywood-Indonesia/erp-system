<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis\Pages;

use App\Filament\Resources\UkuranBarangSetengahJadis\UkuranBarangSetengahJadiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUkuranBarangSetengahJadis extends ListRecords
{
    protected static string $resource = UkuranBarangSetengahJadiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
