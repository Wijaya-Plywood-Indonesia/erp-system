<?php

namespace App\Filament\Resources\MasukGrajiTripleks\Pages;

use App\Filament\Resources\MasukGrajiTripleks\MasukGrajiTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasukGrajiTriplek extends EditRecord
{
    protected static string $resource = MasukGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
