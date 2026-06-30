<?php

namespace App\Filament\Resources\SubAnakAkuns\Pages;

use App\Filament\Resources\SubAnakAkuns\SubAnakAkunResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSubAnakAkun extends EditRecord
{
    protected static string $resource = SubAnakAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
