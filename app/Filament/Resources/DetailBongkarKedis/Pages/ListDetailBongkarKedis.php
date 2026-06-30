<?php

namespace App\Filament\Resources\DetailBongkarKedis\Pages;

use App\Filament\Resources\DetailBongkarKedis\DetailBongkarKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailBongkarKedis extends ListRecords
{
    protected static string $resource = DetailBongkarKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
