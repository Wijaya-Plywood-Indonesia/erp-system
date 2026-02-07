<?php

namespace App\Filament\Resources\KontrakKerjas\Pages;

use App\Filament\Resources\KontrakKerjas\KontrakKerjaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKontrakKerjas extends ListRecords
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

}
