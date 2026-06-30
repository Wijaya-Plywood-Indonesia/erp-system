<?php

namespace App\Filament\Resources\ProduksiRotaries\Pages;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiRotaryForm extends EditRecord
{
    protected static string $resource = ProduksiRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
