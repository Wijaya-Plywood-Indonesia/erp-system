<?php

namespace App\Filament\Resources\ValidasiDempuls\Pages;

use App\Filament\Resources\ValidasiDempuls\ValidasiDempulResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiDempul extends EditRecord
{
    protected static string $resource = ValidasiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
