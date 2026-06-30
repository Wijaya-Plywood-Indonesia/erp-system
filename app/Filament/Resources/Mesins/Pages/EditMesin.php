<?php

namespace App\Filament\Resources\Mesins\Pages;

use App\Filament\Resources\Mesins\MesinResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMesin extends EditRecord
{
    protected static string $resource = MesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
