<?php

namespace App\Filament\Resources\HasilPotAfJoints\Pages;

use App\Filament\Resources\HasilPotAfJoints\HasilPotAfJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilPotAfJoint extends EditRecord
{
    protected static string $resource = HasilPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
