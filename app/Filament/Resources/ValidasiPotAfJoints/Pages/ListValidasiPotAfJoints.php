<?php

namespace App\Filament\Resources\ValidasiPotAfJoints\Pages;

use App\Filament\Resources\ValidasiPotAfJoints\ValidasiPotAfJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPotAfJoints extends ListRecords
{
    protected static string $resource = ValidasiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
