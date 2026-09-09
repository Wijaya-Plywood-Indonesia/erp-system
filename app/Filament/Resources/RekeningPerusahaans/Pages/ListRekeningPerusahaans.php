<?php

namespace App\Filament\Resources\RekeningPerusahaans\Pages;

use App\Filament\Resources\RekeningPerusahaans\RekeningPerusahaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRekeningPerusahaans extends ListRecords
{
    protected static string $resource = RekeningPerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
