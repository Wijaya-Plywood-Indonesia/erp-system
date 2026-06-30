<?php

namespace App\Filament\Resources\KategoriBarangs\Pages;

use App\Filament\Resources\KategoriBarangs\KategoriBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKategoriBarangs extends ListRecords
{
    protected static string $resource = KategoriBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
