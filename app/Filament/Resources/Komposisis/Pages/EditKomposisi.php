<?php

namespace App\Filament\Resources\Komposisis\Pages;

use App\Filament\Resources\Komposisis\KomposisiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKomposisi extends EditRecord
{
    protected static string $resource = KomposisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
