<?php

namespace App\Filament\Resources\ProduksiPotSikus\Pages;

use App\Filament\Resources\ProduksiPotSikus\ProduksiPotSikuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanProduksiPotSikuCustomExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ListProduksiPotSikus extends ListRecords
{
    protected static string $resource = ProduksiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
