<?php

namespace App\Filament\Resources\ValidasiPotAfJoints\Pages;

use App\Filament\Resources\ValidasiPotAfJoints\ValidasiPotAfJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPotAfJoint extends EditRecord
{
    protected static string $resource = ValidasiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
