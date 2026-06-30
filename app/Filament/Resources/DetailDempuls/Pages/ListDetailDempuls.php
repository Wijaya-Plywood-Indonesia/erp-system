<?php

namespace App\Filament\Resources\DetailDempuls\Pages;

use App\Filament\Resources\DetailDempuls\DetailDempulResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailDempuls extends ListRecords
{
    protected static string $resource = DetailDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
