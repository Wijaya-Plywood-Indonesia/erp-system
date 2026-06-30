<?php

namespace App\Filament\Resources\RencanaPegawaiDempuls\Pages;

use App\Filament\Resources\RencanaPegawaiDempuls\RencanaPegawaiDempulResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRencanaPegawaiDempuls extends ListRecords
{
    protected static string $resource = RencanaPegawaiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
