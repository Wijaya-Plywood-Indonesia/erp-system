<?php

namespace App\Filament\Resources\JurnalUmums\Pages;

use App\Filament\Resources\JurnalUmums\JurnalUmumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJurnalUmums extends ListRecords
{
    protected static string $resource = JurnalUmumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
