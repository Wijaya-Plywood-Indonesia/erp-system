<?php

namespace App\Filament\Resources\HargaPegawais\Pages;

use App\Filament\Resources\HargaPegawais\HargaPegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaPegawais extends ListRecords
{
    protected static string $resource = HargaPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
