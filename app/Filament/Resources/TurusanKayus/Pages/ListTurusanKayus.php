<?php

namespace App\Filament\Resources\TurusanKayus\Pages;

use App\Filament\Resources\TurusanKayus\TurusanKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTurusanKayus extends ListRecords
{
    protected static string $resource = TurusanKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
