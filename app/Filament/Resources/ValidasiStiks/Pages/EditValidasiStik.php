<?php

namespace App\Filament\Resources\ValidasiStiks\Pages;

use App\Filament\Resources\ValidasiStiks\ValidasiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiStik extends EditRecord
{
    protected static string $resource = ValidasiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
