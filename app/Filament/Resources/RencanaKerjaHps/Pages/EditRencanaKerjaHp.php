<?php

namespace App\Filament\Resources\RencanaKerjaHps\Pages;

use App\Filament\Resources\RencanaKerjaHps\RencanaKerjaHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRencanaKerjaHp extends EditRecord
{
    protected static string $resource = RencanaKerjaHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
