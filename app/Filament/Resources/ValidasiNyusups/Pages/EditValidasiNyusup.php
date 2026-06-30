<?php

namespace App\Filament\Resources\ValidasiNyusups\Pages;

use App\Filament\Resources\ValidasiNyusups\ValidasiNyusupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiNyusup extends EditRecord
{
    protected static string $resource = ValidasiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
