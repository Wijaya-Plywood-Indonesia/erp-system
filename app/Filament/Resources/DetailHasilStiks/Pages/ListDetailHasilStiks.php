<?php

namespace App\Filament\Resources\DetailHasilStiks\Pages;

use App\Filament\Resources\DetailHasilStiks\DetailHasilStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailHasilStiks extends ListRecords
{
    protected static string $resource = DetailHasilStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
