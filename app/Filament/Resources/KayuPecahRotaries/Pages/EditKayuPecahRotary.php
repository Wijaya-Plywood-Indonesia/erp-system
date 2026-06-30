<?php

namespace App\Filament\Resources\KayuPecahRotaries\Pages;

use App\Filament\Resources\KayuPecahRotaries\KayuPecahRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKayuPecahRotary extends EditRecord
{
    protected static string $resource = KayuPecahRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
