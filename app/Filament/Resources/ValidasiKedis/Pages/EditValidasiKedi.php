<?php

namespace App\Filament\Resources\ValidasiKedis\Pages;

use App\Filament\Resources\ValidasiKedis\ValidasiKediResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiKedi extends EditRecord
{
    protected static string $resource = ValidasiKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
