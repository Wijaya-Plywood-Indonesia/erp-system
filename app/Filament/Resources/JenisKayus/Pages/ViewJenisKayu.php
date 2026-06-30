<?php

namespace App\Filament\Resources\JenisKayus\Pages;

use App\Filament\Resources\JenisKayus\JenisKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJenisKayu extends ViewRecord
{
    protected static string $resource = JenisKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
