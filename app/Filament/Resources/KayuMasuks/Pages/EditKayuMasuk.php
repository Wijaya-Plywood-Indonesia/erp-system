<?php

namespace App\Filament\Resources\KayuMasuks\Pages;

use App\Filament\Resources\KayuMasuks\KayuMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKayuMasuk extends EditRecord
{
    protected static string $resource = KayuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
