<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Pages;

use App\Filament\Resources\PenggunaanLahanRotaries\PenggunaanLahanRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenggunaanLahanRotaries extends ListRecords
{
    protected static string $resource = PenggunaanLahanRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
