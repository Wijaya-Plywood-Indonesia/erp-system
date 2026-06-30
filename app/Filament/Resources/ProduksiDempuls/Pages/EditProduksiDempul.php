<?php

namespace App\Filament\Resources\ProduksiDempuls\Pages;

use App\Filament\Resources\ProduksiDempuls\ProduksiDempulResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiDempul extends EditRecord
{
    protected static string $resource = ProduksiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
