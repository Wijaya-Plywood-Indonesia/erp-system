<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Pages;

use App\Filament\Resources\ProduksiPotJeleks\ProduksiPotJelekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPotJelek extends EditRecord
{
    protected static string $resource = ProduksiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
