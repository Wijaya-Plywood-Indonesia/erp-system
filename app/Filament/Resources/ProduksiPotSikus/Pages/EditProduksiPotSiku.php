<?php

namespace App\Filament\Resources\ProduksiPotSikus\Pages;

use App\Filament\Resources\ProduksiPotSikus\ProduksiPotSikuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPotSiku extends EditRecord
{
    protected static string $resource = ProduksiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
