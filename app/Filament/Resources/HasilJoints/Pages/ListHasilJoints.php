<?php

namespace App\Filament\Resources\HasilJoints\Pages;

use App\Filament\Resources\HasilJoints\HasilJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilJoints extends ListRecords
{
    protected static string $resource = HasilJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
