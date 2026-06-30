<?php

namespace App\Filament\Resources\DetailTurunKayus\Pages;

use App\Filament\Resources\DetailTurunKayus\DetailTurunKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailTurunKayus extends ListRecords
{
    protected static string $resource = DetailTurunKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
