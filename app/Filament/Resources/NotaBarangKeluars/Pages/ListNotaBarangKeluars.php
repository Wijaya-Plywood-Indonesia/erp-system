<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Actions\ExportRekapAction;
use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotaBarangKeluars extends ListRecords
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportRekapAction::make('bk'),

            CreateAction::make()
                ->label('Buat Nota BK'),
        ];
    }
}
