<?php

namespace App\Filament\Resources\Jurnal2s\Pages;

use App\Filament\Resources\Jurnal2s\Jurnal2Resource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJurnal2 extends EditRecord
{
    protected static string $resource = Jurnal2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
