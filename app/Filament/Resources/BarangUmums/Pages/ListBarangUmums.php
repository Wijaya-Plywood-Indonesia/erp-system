<?php

namespace App\Filament\Resources\BarangUmums\Pages;

use App\Filament\Resources\BarangUmums\BarangUmumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBarangUmums extends ListRecords
{
    protected static string $resource = BarangUmumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
