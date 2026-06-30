<?php

namespace App\Filament\Resources\BahanProduksis\Pages;

use App\Filament\Resources\BahanProduksis\BahanProduksiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanProduksi extends EditRecord
{
    protected static string $resource = BahanProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
