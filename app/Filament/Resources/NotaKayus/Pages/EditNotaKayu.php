<?php

namespace App\Filament\Resources\NotaKayus\Pages;

use App\Filament\Resources\NotaKayus\NotaKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNotaKayu extends EditRecord
{
    protected static string $resource = NotaKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
