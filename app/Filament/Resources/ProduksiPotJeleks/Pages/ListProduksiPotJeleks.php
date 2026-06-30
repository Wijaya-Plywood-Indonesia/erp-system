<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Pages;

use App\Filament\Resources\ProduksiPotJeleks\ProduksiPotJelekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanProduksiPotJelekCustomExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ListProduksiPotJeleks extends ListRecords
{
    protected static string $resource = ProduksiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
