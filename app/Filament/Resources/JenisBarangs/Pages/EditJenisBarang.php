<?php

namespace App\Filament\Resources\JenisBarangs\Pages;

use App\Filament\Resources\JenisBarangs\JenisBarangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisBarang extends EditRecord
{
    protected static string $resource = JenisBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
