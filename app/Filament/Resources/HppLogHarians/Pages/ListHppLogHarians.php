<?php

namespace App\Filament\Resources\HppLogHarians\Pages;

use App\Filament\Resources\HppLogHarians\HppLogHarianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHppLogHarians extends ListRecords
{
    protected static string $resource = HppLogHarianResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
