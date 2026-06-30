<?php

namespace App\Filament\Resources\ValidasiSandings\Pages;

use App\Filament\Resources\ValidasiSandings\ValidasiSandingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiSanding extends EditRecord
{
    protected static string $resource = ValidasiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
