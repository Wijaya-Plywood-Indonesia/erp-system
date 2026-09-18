<?php

namespace App\Filament\Resources\RekeningPerusahaans\Pages;

use App\Filament\Resources\RekeningPerusahaans\RekeningPerusahaanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRekeningPerusahaan extends EditRecord
{
    protected static string $resource = RekeningPerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
