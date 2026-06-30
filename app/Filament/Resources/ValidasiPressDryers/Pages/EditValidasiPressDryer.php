<?php

namespace App\Filament\Resources\ValidasiPressDryers\Pages;

use App\Filament\Resources\ValidasiPressDryers\ValidasiPressDryerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPressDryer extends EditRecord
{
    protected static string $resource = ValidasiPressDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
