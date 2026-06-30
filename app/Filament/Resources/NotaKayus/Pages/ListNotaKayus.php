<?php

namespace App\Filament\Resources\NotaKayus\Pages;

use App\Filament\Resources\NotaKayus\NotaKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotaKayus extends ListRecords
{
    protected static string $resource = NotaKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
