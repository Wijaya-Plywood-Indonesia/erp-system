<?php

namespace App\Filament\Resources\RencanaRepairs\Pages;

use App\Filament\Resources\RencanaRepairs\RencanaRepairResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRencanaRepair extends EditRecord
{
    protected static string $resource = RencanaRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
