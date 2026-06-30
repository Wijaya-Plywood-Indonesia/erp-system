<?php

namespace App\Filament\Resources\ValidasiSandingJoints\Pages;

use App\Filament\Resources\ValidasiSandingJoints\ValidasiSandingJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiSandingJoints extends ListRecords
{
    protected static string $resource = ValidasiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
