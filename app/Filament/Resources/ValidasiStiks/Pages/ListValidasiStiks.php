<?php

namespace App\Filament\Resources\ValidasiStiks\Pages;

use App\Filament\Resources\ValidasiStiks\ValidasiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiStiks extends ListRecords
{
    protected static string $resource = ValidasiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
