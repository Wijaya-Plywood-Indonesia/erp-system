<?php

namespace App\Filament\Resources\PegawaiSandingJoints\Pages;

use App\Filament\Resources\PegawaiSandingJoints\PegawaiSandingJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiSandingJoints extends ListRecords
{
    protected static string $resource = PegawaiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
