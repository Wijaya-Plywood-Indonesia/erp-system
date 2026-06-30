<?php

namespace App\Filament\Resources\Neracas\Pages;

use App\Filament\Resources\Neracas\NeracaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNeraca extends EditRecord
{
    protected static string $resource = NeracaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
