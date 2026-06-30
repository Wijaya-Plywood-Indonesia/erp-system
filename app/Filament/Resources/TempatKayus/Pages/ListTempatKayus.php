<?php

namespace App\Filament\Resources\TempatKayus\Pages;

use App\Filament\Resources\TempatKayus\TempatKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTempatKayus extends ListRecords
{
    protected static string $resource = TempatKayuResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make(),
    //     ];
    // }
}
