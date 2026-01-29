<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Pages;

use App\Filament\Resources\ProduksiPilihVeneers\ProduksiPilihVeneerResource;
use App\Filament\Resources\ProduksiPilihVeneers\Widgets\ProduksiPilihVeneerSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPilihVeneer extends ViewRecord
{
    protected static string $resource = ProduksiPilihVeneerResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPilihVeneerSummaryWidget::class,
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
