<?php

namespace App\Filament\Resources\Ukurans\Pages;

use App\Filament\Resources\Ukurans\UkuranResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUkuran extends EditRecord
{
    protected static string $resource = UkuranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
