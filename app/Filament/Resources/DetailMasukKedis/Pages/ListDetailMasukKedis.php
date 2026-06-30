<?php

namespace App\Filament\Resources\DetailMasukKedis\Pages;

use App\Filament\Resources\DetailMasukKedis\DetailMasukKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailMasukKedis extends ListRecords
{
    protected static string $resource = DetailMasukKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
