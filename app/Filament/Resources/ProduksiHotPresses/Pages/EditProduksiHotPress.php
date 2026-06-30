<?php

namespace App\Filament\Resources\ProduksiHotPresses\Pages;

use App\Filament\Resources\ProduksiHotPresses\ProduksiHotPressResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiHotPress extends EditRecord
{
    protected static string $resource = ProduksiHotPressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
