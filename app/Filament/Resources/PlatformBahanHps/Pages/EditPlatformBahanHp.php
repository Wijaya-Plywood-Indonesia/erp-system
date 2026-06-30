<?php

namespace App\Filament\Resources\PlatformBahanHps\Pages;

use App\Filament\Resources\PlatformBahanHps\PlatformBahanHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformBahanHp extends EditRecord
{
    protected static string $resource = PlatformBahanHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
