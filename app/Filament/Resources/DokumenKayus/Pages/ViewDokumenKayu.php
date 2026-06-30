<?php

namespace App\Filament\Resources\DokumenKayus\Pages;

use App\Filament\Resources\DokumenKayus\DokumenKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDokumenKayu extends ViewRecord
{
    protected static string $resource = DokumenKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
