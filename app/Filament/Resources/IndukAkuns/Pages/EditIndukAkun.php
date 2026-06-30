<?php

namespace App\Filament\Resources\IndukAkuns\Pages;

use App\Filament\Resources\IndukAkuns\IndukAkunResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIndukAkun extends EditRecord
{
    protected static string $resource = IndukAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
