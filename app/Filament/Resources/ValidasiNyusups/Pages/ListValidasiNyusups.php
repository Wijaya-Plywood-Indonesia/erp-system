<?php

namespace App\Filament\Resources\ValidasiNyusups\Pages;

use App\Filament\Resources\ValidasiNyusups\ValidasiNyusupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiNyusups extends ListRecords
{
    protected static string $resource = ValidasiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
