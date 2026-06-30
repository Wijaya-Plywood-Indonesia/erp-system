<?php

namespace App\Filament\Resources\VeneerBahanHps\Pages;

use App\Filament\Resources\VeneerBahanHps\VeneerBahanHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVeneerBahanHp extends EditRecord
{
    protected static string $resource = VeneerBahanHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
