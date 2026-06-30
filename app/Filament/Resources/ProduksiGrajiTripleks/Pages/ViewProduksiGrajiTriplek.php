<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Pages;

use App\Filament\Resources\ProduksiGrajiTripleks\ProduksiGrajiTriplekResource;
use App\Filament\Resources\ProduksiGrajiTripleks\Widgets\ProduksiGrajiSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiGrajiTriplek extends ViewRecord
{
    protected static string $resource = ProduksiGrajiTriplekResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiGrajiSummaryWidget::class,
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
