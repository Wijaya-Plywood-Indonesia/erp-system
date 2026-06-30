<?php

namespace App\Filament\Resources\HasilSandingJoints\Pages;

use App\Filament\Resources\HasilSandingJoints\HasilSandingJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilSandingJoint extends EditRecord
{
    protected static string $resource = HasilSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
