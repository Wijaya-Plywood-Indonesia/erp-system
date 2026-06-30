<?php

namespace App\Filament\Resources\ModalJoints\Pages;

use App\Filament\Resources\ModalJoints\ModalJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModalJoints extends ListRecords
{
    protected static string $resource = ModalJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
