<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Pages;

use App\Filament\Resources\ProduksiPotJeleks\ProduksiPotJelekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanProduksiPotJelekCustomExport;
use App\Filament\Resources\ProduksiPotJeleks\Widgets\RekapProduksiPotJelek;
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

    protected function getHeaderWidgets(): array
    {
        return [
            RekapProduksiPotJelek::class,
        ];
    }
}
