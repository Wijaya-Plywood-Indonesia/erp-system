<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Pages;

use App\Filament\Resources\ProduksiPilihPlywoods\ProduksiPilihPlywoodResource;
use App\Filament\Resources\ProduksiPilihPlywoods\Widgets\ProduksiPilihPlywoodSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPilihPlywood extends ViewRecord
{
    protected static string $resource = ProduksiPilihPlywoodResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPilihPlywoodSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
            // ->hidden(function () {
            //     $record = $this->getRecord();

            //     // Jika tidak ada validasi → tombol tetap muncul
            //     if (!$record->validasiTerakhir) {
            //         return false;
            //     }

            //     // Jika status terakhir = divalidasi → sembunyikan tombol
            //     return $record->validasiTerakhir->status === 'divalidasi';
            // }),
        ];
    }

    
}
