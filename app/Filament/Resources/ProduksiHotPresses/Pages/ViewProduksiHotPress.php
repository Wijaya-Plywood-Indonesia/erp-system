<?php

namespace App\Filament\Resources\ProduksiHotPresses\Pages;

use App\Filament\Resources\ProduksiHotPresses\ProduksiHotPressResource;
use App\Filament\Resources\ProduksiHotPresses\Widgets\ProduksiHotPressSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiHotPress extends ViewRecord
{
    protected static string $resource = ProduksiHotPressResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiHotPressSummaryWidget::class,
        ];
    }


    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(function () {
                    $record = $this->getRecord();

                    // Jika tidak ada validasi → tombol tetap muncul
                    if (!$record->validasiTerakhir) {
                        return false;
                    }

                    // Jika status terakhir = divalidasi → sembunyikan tombol
                    return $record->validasiTerakhir->status === 'divalidasi';
                }),
        ];
    }
}
