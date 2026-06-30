<?php

namespace App\Filament\Resources\ValidasiPotJeleks\Pages;

use App\Filament\Resources\ValidasiPotJeleks\ValidasiPotJelekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPotJelek extends EditRecord
{
    protected static string $resource = ValidasiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
