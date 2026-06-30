<?php

namespace App\Filament\Resources\PegawaiPotAfJoints\Pages;

use App\Filament\Resources\PegawaiPotAfJoints\PegawaiPotAfJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiPotAfJoint extends EditRecord
{
    protected static string $resource = PegawaiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
