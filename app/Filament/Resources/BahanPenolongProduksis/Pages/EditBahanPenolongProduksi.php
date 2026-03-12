<?php

namespace App\Filament\Resources\BahanPenolongProduksis\Pages;

use App\Filament\Resources\BahanPenolongProduksis\BahanPenolongProduksiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanPenolongProduksi extends EditRecord
{
    protected static string $resource = BahanPenolongProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
