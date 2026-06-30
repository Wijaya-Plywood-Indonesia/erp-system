<?php

namespace App\Filament\Resources\PegawaiPotSikus\Pages;

use App\Filament\Resources\PegawaiPotSikus\PegawaiPotSikuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiPotSiku extends EditRecord
{
    protected static string $resource = PegawaiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
