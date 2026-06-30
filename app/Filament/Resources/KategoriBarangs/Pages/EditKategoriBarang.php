<?php

namespace App\Filament\Resources\KategoriBarangs\Pages;

use App\Filament\Resources\KategoriBarangs\KategoriBarangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKategoriBarang extends EditRecord
{
    protected static string $resource = KategoriBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
