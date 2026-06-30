<?php

namespace App\Filament\Resources\JenisBarangs\Pages;

use App\Filament\Resources\JenisBarangs\JenisBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisBarangs extends ListRecords
{
    protected static string $resource = JenisBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
