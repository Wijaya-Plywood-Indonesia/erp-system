<?php

namespace App\Filament\Resources\DetailKayuMasuks\Pages;

use App\Filament\Resources\DetailKayuMasuks\DetailKayuMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailKayuMasuk extends EditRecord
{
    protected static string $resource = DetailKayuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
