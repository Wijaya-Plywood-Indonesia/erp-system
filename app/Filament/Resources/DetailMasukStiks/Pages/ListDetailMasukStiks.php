<?php

namespace App\Filament\Resources\DetailMasukStiks\Pages;

use App\Filament\Resources\DetailMasukStiks\DetailMasukStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailMasukStiks extends ListRecords
{
    protected static string $resource = DetailMasukStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
