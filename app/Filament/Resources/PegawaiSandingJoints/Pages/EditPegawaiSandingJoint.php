<?php

namespace App\Filament\Resources\PegawaiSandingJoints\Pages;

use App\Filament\Resources\PegawaiSandingJoints\PegawaiSandingJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiSandingJoint extends EditRecord
{
    protected static string $resource = PegawaiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
