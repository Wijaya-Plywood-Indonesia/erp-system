<?php

namespace App\Filament\Resources\KontrakKerjas\Pages;

use App\Filament\Resources\KontrakKerjas\KontrakKerjaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKontrakKerja extends EditRecord
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
