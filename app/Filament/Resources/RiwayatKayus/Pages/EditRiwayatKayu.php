<?php

namespace App\Filament\Resources\RiwayatKayus\Pages;

use App\Filament\Resources\RiwayatKayus\RiwayatKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRiwayatKayu extends EditRecord
{
    protected static string $resource = RiwayatKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
