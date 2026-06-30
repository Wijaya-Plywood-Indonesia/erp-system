<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Pages;

use App\Filament\Resources\ProduksiGrajiTripleks\ProduksiGrajiTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiGrajiTriplek extends EditRecord
{
    protected static string $resource = ProduksiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
