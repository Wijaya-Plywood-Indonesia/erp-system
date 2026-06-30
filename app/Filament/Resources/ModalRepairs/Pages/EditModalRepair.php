<?php

namespace App\Filament\Resources\ModalRepairs\Pages;

use App\Filament\Resources\ModalRepairs\ModalRepairResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModalRepair extends EditRecord
{
    protected static string $resource = ModalRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
