<?php

namespace App\Filament\Resources\PegawaiPilihPlywoods\Pages;

use App\Filament\Resources\PegawaiPilihPlywoods\PegawaiPilihPlywoodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiPilihPlywood extends EditRecord
{
    protected static string $resource = PegawaiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
