<?php

namespace App\Filament\Resources\IndukAkuns\Pages;

use App\Filament\Resources\IndukAkuns\IndukAkunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndukAkuns extends ListRecords
{
    protected static string $resource = IndukAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
