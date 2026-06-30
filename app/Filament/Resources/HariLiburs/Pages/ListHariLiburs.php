<?php

namespace App\Filament\Resources\HariLiburs\Pages;

use App\Filament\Resources\HariLiburs\HariLiburResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHariLiburs extends ListRecords
{
    protected static string $resource = HariLiburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected int $defaultPaginationPageSize = 50;
}
