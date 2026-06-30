<?php

namespace App\Filament\Resources\RiwayatKayus\Pages;

use App\Filament\Resources\RiwayatKayus\RiwayatKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRiwayatKayus extends ListRecords
{
    protected static string $resource = RiwayatKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
