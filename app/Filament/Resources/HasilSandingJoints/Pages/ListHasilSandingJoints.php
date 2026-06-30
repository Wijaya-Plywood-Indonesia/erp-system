<?php

namespace App\Filament\Resources\HasilSandingJoints\Pages;

use App\Filament\Resources\HasilSandingJoints\HasilSandingJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilSandingJoints extends ListRecords
{
    protected static string $resource = HasilSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
