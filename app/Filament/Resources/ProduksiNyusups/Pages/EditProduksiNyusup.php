<?php

namespace App\Filament\Resources\ProduksiNyusups\Pages;

use App\Filament\Resources\ProduksiNyusups\ProduksiNyusupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiNyusup extends EditRecord
{
    protected static string $resource = ProduksiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
