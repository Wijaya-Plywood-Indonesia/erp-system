<?php

namespace App\Filament\Resources\ModalJoints\Pages;

use App\Filament\Resources\ModalJoints\ModalJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModalJoint extends EditRecord
{
    protected static string $resource = ModalJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
