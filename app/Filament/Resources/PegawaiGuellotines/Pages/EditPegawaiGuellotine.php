<?php

namespace App\Filament\Resources\PegawaiGuellotines\Pages;

use App\Filament\Resources\PegawaiGuellotines\PegawaiGuellotineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiGuellotine extends EditRecord
{
    protected static string $resource = PegawaiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
