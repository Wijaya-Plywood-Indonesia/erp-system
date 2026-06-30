<?php

namespace App\Filament\Resources\BahanDempuls\Pages;

use App\Filament\Resources\BahanDempuls\BahanDempulResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanDempuls extends ListRecords
{
    protected static string $resource = BahanDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
