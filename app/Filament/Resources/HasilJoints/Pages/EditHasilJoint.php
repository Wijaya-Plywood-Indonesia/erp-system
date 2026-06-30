<?php

namespace App\Filament\Resources\HasilJoints\Pages;

use App\Filament\Resources\HasilJoints\HasilJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilJoint extends EditRecord
{
    protected static string $resource = HasilJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
