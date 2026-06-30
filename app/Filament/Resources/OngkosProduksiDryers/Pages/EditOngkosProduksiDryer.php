<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Pages;

use App\Filament\Resources\OngkosProduksiDryers\OngkosProduksiDryerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOngkosProduksiDryer extends EditRecord
{
    protected static string $resource = OngkosProduksiDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
