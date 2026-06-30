<?php

namespace App\Filament\Resources\ValidasiRepairs\Pages;

use App\Filament\Resources\ValidasiRepairs\ValidasiRepairResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiRepair extends EditRecord
{
    protected static string $resource = ValidasiRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
