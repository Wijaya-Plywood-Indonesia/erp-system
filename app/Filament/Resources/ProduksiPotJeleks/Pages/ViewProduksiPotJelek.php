<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Pages;

use App\Filament\Resources\ProduksiPotJeleks\ProduksiPotJelekResource;
use App\Filament\Resources\ProduksiPotJeleks\Widgets\ProduksiPotJelekSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPotJelek extends ViewRecord
{
    protected static string $resource = ProduksiPotJelekResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPotJelekSummaryWidget::class,
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
