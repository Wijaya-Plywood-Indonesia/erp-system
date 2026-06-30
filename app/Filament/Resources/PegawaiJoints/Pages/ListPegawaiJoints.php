<?php

namespace App\Filament\Resources\PegawaiJoints\Pages;

use App\Filament\Resources\PegawaiJoints\PegawaiJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiJoints extends ListRecords
{
    protected static string $resource = PegawaiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
