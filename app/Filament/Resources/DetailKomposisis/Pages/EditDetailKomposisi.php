<?php

namespace App\Filament\Resources\DetailKomposisis\Pages;

use App\Filament\Resources\DetailKomposisis\DetailKomposisiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailKomposisi extends EditRecord
{
    protected static string $resource = DetailKomposisiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
