<?php

namespace App\Filament\Resources\BarangUmums\Pages;

use App\Filament\Resources\BarangUmums\BarangUmumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBarangUmum extends EditRecord
{
    protected static string $resource = BarangUmumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
