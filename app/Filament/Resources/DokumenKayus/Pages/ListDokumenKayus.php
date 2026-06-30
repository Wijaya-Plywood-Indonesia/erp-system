<?php

namespace App\Filament\Resources\DokumenKayus\Pages;

use App\Filament\Resources\DokumenKayus\DokumenKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDokumenKayus extends ListRecords
{
    protected static string $resource = DokumenKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
