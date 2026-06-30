<?php

namespace App\Filament\Resources\BahanPilihPlywoods\Pages;

use App\Filament\Resources\BahanPilihPlywoods\BahanPilihPlywoodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanPilihPlywood extends EditRecord
{
    protected static string $resource = BahanPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
