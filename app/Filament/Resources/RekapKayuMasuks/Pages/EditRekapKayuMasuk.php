<?php

namespace App\Filament\Resources\RekapKayuMasuks\Pages;

use App\Filament\Resources\RekapKayuMasuks\RekapKayuMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRekapKayuMasuk extends EditRecord
{
    protected static string $resource = RekapKayuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
