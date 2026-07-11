<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\Pages;

use App\Filament\Resources\ProduksiTembelTripleks\ProduksiTembelTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiTembelTriplek extends EditRecord
{
    protected static string $resource = ProduksiTembelTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
