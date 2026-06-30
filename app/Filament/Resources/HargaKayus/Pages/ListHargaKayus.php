<?php

namespace App\Filament\Resources\HargaKayus\Pages;

use App\Filament\Resources\HargaKayus\HargaKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaKayus extends ListRecords
{
    protected static string $resource = HargaKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
