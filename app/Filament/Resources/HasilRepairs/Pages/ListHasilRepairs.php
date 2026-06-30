<?php

namespace App\Filament\Resources\HasilRepairs\Pages;

use App\Filament\Resources\HasilRepairs\HasilRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilRepairs extends ListRecords
{
    protected static string $resource = HasilRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
