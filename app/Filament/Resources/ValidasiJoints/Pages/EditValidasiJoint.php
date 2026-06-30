<?php

namespace App\Filament\Resources\ValidasiJoints\Pages;

use App\Filament\Resources\ValidasiJoints\ValidasiJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiJoint extends EditRecord
{
    protected static string $resource = ValidasiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
