<?php

namespace App\Filament\Resources\Lahans\Pages;

use App\Filament\Resources\Lahans\LahanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLahans extends ListRecords
{
    protected static string $resource = LahanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
