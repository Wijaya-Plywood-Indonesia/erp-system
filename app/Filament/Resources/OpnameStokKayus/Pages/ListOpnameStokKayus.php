<?php

namespace App\Filament\Resources\OpnameStokKayus\Pages;

use App\Filament\Resources\OpnameStokKayus\OpnameStokKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpnameStokKayus extends ListRecords
{
    protected static string $resource = OpnameStokKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
