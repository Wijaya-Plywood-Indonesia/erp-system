<?php

namespace App\Filament\Resources\TempatKayus\Pages;

use App\Filament\Resources\TempatKayus\TempatKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTempatKayu extends EditRecord
{
    protected static string $resource = TempatKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
