<?php

namespace App\Filament\Resources\LainLains\Pages;

use App\Filament\Resources\LainLains\LainLainResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLainLain extends EditRecord
{
    protected static string $resource = LainLainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
