<?php

namespace App\Filament\Resources\BahanHotPresses\Pages;

use App\Filament\Resources\BahanHotPresses\BahanHotPressResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanHotPresses extends ListRecords
{
    protected static string $resource = BahanHotPressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
