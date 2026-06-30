<?php

namespace App\Filament\Resources\BahanHotPresses\Pages;

use App\Filament\Resources\BahanHotPresses\BahanHotPressResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanHotPress extends EditRecord
{
    protected static string $resource = BahanHotPressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
