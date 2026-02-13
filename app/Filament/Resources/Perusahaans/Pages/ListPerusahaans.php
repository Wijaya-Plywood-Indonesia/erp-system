<?php

namespace App\Filament\Resources\Perusahaans\Pages;

use App\Filament\Resources\Perusahaans\PerusahaanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerusahaans extends ListRecords
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
