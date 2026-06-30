<?php

namespace App\Filament\Resources\PegawaiPotJeleks\Pages;

use App\Filament\Resources\PegawaiPotJeleks\PegawaiPotJelekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiPotJelek extends EditRecord
{
    protected static string $resource = PegawaiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
