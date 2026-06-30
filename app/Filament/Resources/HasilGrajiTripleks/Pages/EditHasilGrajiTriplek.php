<?php

namespace App\Filament\Resources\HasilGrajiTripleks\Pages;

use App\Filament\Resources\HasilGrajiTripleks\HasilGrajiTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilGrajiTriplek extends EditRecord
{
    protected static string $resource = HasilGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
