<?php

namespace App\Filament\Resources\ProduksiPalets\Pages;

use App\Filament\Resources\ProduksiPalets\ProduksiPaletResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPalet extends EditRecord
{
    protected static string $resource = ProduksiPaletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
