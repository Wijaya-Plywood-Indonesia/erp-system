<?php

namespace App\Filament\Resources\PegawaiNyusups\Pages;

use App\Filament\Resources\PegawaiNyusups\PegawaiNyusupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiNyusups extends ListRecords
{
    protected static string $resource = PegawaiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
