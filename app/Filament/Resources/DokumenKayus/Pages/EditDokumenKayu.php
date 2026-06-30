<?php

namespace App\Filament\Resources\DokumenKayus\Pages;

use App\Filament\Resources\DokumenKayus\DokumenKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDokumenKayu extends EditRecord
{
    protected static string $resource = DokumenKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
