<?php

namespace App\Filament\Resources\ValidasiKedis\Pages;

use App\Filament\Resources\ValidasiKedis\ValidasiKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiKedis extends ListRecords
{
    protected static string $resource = ValidasiKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
