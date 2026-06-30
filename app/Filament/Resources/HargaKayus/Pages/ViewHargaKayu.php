<?php

namespace App\Filament\Resources\HargaKayus\Pages;

use App\Filament\Resources\HargaKayus\HargaKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHargaKayu extends ViewRecord
{
    protected static string $resource = HargaKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
