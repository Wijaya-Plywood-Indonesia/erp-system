<?php

namespace App\Filament\Resources\Ukurans\Pages;

use App\Filament\Resources\Ukurans\UkuranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUkurans extends ListRecords
{
    protected static string $resource = UkuranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
