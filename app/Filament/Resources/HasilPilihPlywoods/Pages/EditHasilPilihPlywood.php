<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Pages;

use App\Filament\Resources\HasilPilihPlywoods\HasilPilihPlywoodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilPilihPlywood extends EditRecord
{
    protected static string $resource = HasilPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
