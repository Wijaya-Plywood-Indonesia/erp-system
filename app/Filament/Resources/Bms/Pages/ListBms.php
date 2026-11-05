<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBms extends ListRecords
{
    protected static string $resource = BmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Resources\Bms\Actions\BuatBmlDariBklAction::make(),
            CreateAction::make()->label('Buat BM'),
        ];
    }
}
