<?php

namespace App\Filament\Resources\HasilSandings\Pages;

use App\Filament\Resources\HasilSandings\HasilSandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilSandings extends ListRecords
{
    protected static string $resource = HasilSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
