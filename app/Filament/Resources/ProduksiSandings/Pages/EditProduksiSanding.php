<?php

namespace App\Filament\Resources\ProduksiSandings\Pages;

use App\Filament\Resources\ProduksiSandings\ProduksiSandingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiSanding extends EditRecord
{
    protected static string $resource = ProduksiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
