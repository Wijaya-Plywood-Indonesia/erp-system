<?php

namespace App\Filament\Resources\JurnalTigas\Pages;

use App\Filament\Resources\JurnalTigas\JurnalTigaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJurnalTiga extends EditRecord
{
    protected static string $resource = JurnalTigaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
