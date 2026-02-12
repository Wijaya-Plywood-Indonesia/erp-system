<?php

namespace App\Filament\Resources\Perusahaans\Pages;

use App\Filament\Resources\Perusahaans\PerusahaanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerusahaan extends EditRecord
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
