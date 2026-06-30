<?php

namespace App\Filament\Resources\PegawaiJoints\Pages;

use App\Filament\Resources\PegawaiJoints\PegawaiJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiJoint extends EditRecord
{
    protected static string $resource = PegawaiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
