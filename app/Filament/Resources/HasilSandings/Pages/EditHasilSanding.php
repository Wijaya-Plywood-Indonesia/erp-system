<?php

namespace App\Filament\Resources\HasilSandings\Pages;

use App\Filament\Resources\HasilSandings\HasilSandingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilSanding extends EditRecord
{
    protected static string $resource = HasilSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
