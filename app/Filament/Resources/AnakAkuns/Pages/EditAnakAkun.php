<?php

namespace App\Filament\Resources\AnakAkuns\Pages;

use App\Filament\Resources\AnakAkuns\AnakAkunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnakAkun extends EditRecord
{
    protected static string $resource = AnakAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
