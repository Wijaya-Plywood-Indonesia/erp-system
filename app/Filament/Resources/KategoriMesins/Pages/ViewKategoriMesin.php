<?php

namespace App\Filament\Resources\KategoriMesins\Pages;

use App\Filament\Resources\KategoriMesins\KategoriMesinResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKategoriMesin extends ViewRecord
{
    protected static string $resource = KategoriMesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
