<?php

namespace App\Filament\Resources\DetailTurusanKayus\Pages;

use App\Filament\Resources\DetailTurusanKayus\DetailTurusanKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailTurusanKayus extends ListRecords
{
    protected static string $resource = DetailTurusanKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
