<?php

namespace App\Filament\Resources\ValidasiPotSikus\Pages;

use App\Filament\Resources\ValidasiPotSikus\ValidasiPotSikuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPotSiku extends EditRecord
{
    protected static string $resource = ValidasiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
