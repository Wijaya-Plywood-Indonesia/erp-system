<?php

namespace App\Filament\Resources\PengajuanBarangs\Pages;

use App\Filament\Resources\PengajuanBarangs\PengajuanBarangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengajuanBarangs extends ListRecords
{
    protected static string $resource = PengajuanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajukan Barang'),
        ];
    }
}