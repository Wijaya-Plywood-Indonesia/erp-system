<?php

namespace App\Filament\Resources\TotalSolasis\Pages;

use App\Filament\Resources\TotalSolasis\TotalSolasiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTotalSolasi extends EditRecord
{
    protected static string $resource = TotalSolasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
