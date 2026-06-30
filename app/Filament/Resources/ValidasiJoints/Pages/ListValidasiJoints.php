<?php

namespace App\Filament\Resources\ValidasiJoints\Pages;

use App\Filament\Resources\ValidasiJoints\ValidasiJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiJoints extends ListRecords
{
    protected static string $resource = ValidasiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
