<?php

namespace App\Filament\Resources\AkunGroups\Pages;

use App\Filament\Resources\AkunGroups\AkunGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAkunGroup extends EditRecord
{
    protected static string $resource = AkunGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
