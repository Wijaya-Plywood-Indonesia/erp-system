<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Pages;

use App\Filament\Resources\ListPekerjaanMenumpuks\ListPekerjaanMenumpukResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditListPekerjaanMenumpuk extends EditRecord
{
    protected static string $resource = ListPekerjaanMenumpukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
