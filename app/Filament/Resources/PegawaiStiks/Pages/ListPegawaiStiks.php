<?php

namespace App\Filament\Resources\PegawaiStiks\Pages;

use App\Filament\Resources\PegawaiStiks\PegawaiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiStiks extends ListRecords
{
    protected static string $resource = PegawaiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
