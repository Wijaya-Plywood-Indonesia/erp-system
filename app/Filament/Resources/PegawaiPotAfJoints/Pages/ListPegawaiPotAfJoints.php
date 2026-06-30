<?php

namespace App\Filament\Resources\PegawaiPotAfJoints\Pages;

use App\Filament\Resources\PegawaiPotAfJoints\PegawaiPotAfJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiPotAfJoints extends ListRecords
{
    protected static string $resource = PegawaiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
