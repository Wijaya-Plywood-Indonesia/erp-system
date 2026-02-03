<?php

namespace App\Filament\Resources\Jurnal2s\Pages;

use App\Filament\Resources\Jurnal2s\Jurnal2Resource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJurnal2s extends ListRecords
{
    protected static string $resource = Jurnal2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
