<?php

namespace App\Filament\Resources\DetailHasils\Pages;

use App\Filament\Resources\DetailHasils\DetailHasilResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailHasil extends EditRecord
{
    protected static string $resource = DetailHasilResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
