<?php

namespace App\Filament\Resources\ProduksiGuellotines\Pages;

use App\Filament\Resources\ProduksiGuellotines\ProduksiGuellotineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiGuellotine extends EditRecord
{
    protected static string $resource = ProduksiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
