<?php

namespace App\Filament\Resources\ValidasiPilihPlywoods\Pages;

use App\Filament\Resources\ValidasiPilihPlywoods\ValidasiPilihPlywoodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPilihPlywood extends EditRecord
{
    protected static string $resource = ValidasiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
