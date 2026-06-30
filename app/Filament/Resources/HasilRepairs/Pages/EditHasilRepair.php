<?php

namespace App\Filament\Resources\HasilRepairs\Pages;

use App\Filament\Resources\HasilRepairs\HasilRepairResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilRepair extends EditRecord
{
    protected static string $resource = HasilRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
