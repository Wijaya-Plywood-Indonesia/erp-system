<?php

namespace App\Filament\Resources\HasilPotAfJoints\Pages;

use App\Filament\Resources\HasilPotAfJoints\HasilPotAfJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilPotAfJoints extends ListRecords
{
    protected static string $resource = HasilPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
