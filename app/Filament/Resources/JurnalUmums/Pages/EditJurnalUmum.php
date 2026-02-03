<?php

namespace App\Filament\Resources\JurnalUmums\Pages;

use App\Filament\Resources\JurnalUmums\JurnalUmumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJurnalUmum extends EditRecord
{
    protected static string $resource = JurnalUmumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
