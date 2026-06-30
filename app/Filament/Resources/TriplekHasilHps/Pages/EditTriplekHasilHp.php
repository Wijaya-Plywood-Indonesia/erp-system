<?php

namespace App\Filament\Resources\TriplekHasilHps\Pages;

use App\Filament\Resources\TriplekHasilHps\TriplekHasilHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTriplekHasilHp extends EditRecord
{
    protected static string $resource = TriplekHasilHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
