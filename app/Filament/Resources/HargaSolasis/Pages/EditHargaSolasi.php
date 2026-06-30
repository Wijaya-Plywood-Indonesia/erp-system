<?php

namespace App\Filament\Resources\HargaSolasis\Pages;

use App\Filament\Resources\HargaSolasis\HargaSolasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHargaSolasi extends EditRecord
{
    protected static string $resource = HargaSolasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
