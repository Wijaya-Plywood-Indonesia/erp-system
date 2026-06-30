<?php

namespace App\Filament\Resources\PlatformHasilHps\Pages;

use App\Filament\Resources\PlatformHasilHps\PlatformHasilHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformHasilHp extends EditRecord
{
    protected static string $resource = PlatformHasilHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
