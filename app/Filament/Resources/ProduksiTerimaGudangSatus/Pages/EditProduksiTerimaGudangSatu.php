<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Pages;

use App\Filament\Resources\ProduksiTerimaGudangSatus\ProduksiTerimaGudangSatuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiTerimaGudangSatu extends EditRecord
{
    protected static string $resource = ProduksiTerimaGudangSatuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
