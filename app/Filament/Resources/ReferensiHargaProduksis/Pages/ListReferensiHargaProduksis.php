<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Pages;

use App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferensiHargaProduksis extends ListRecords
{
    protected static string $resource = ReferensiHargaProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
