<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Pages;

use App\Filament\Resources\ProduksiPilihPlywoods\ProduksiPilihPlywoodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPilihPlywood extends EditRecord
{
    protected static string $resource = ProduksiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
