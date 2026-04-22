<?php

namespace App\Filament\Resources\OpnameStokKayus\Pages;

use App\Filament\Resources\OpnameStokKayus\OpnameStokKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOpnameStokKayu extends EditRecord
{
    protected static string $resource = OpnameStokKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
