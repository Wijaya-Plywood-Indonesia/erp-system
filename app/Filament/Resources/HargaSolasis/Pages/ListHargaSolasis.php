<?php

namespace App\Filament\Resources\HargaSolasis\Pages;

use App\Filament\Resources\HargaSolasis\HargaSolasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaSolasis extends ListRecords
{
    protected static string $resource = HargaSolasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
