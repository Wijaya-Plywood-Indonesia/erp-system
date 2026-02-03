<?php

namespace App\Filament\Resources\Jurnal1sts\Pages;

use App\Filament\Resources\Jurnal1sts\Jurnal1stResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJurnal1sts extends ListRecords
{
    protected static string $resource = Jurnal1stResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
