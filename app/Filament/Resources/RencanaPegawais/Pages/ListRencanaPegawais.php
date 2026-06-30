<?php

namespace App\Filament\Resources\RencanaPegawais\Pages;

use App\Filament\Resources\RencanaPegawais\RencanaPegawaiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRencanaPegawais extends ListRecords
{
    protected static string $resource = RencanaPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
