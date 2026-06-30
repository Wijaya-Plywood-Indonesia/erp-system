<?php

namespace App\Filament\Resources\KayuPecahRotaries\Pages;

use App\Filament\Resources\KayuPecahRotaries\KayuPecahRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKayuPecahRotaries extends ListRecords
{
    protected static string $resource = KayuPecahRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
