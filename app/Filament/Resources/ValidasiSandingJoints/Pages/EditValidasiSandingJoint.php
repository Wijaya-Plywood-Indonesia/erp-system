<?php

namespace App\Filament\Resources\ValidasiSandingJoints\Pages;

use App\Filament\Resources\ValidasiSandingJoints\ValidasiSandingJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiSandingJoint extends EditRecord
{
    protected static string $resource = ValidasiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
