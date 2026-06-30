<?php

namespace App\Filament\Resources\ProduksiPressDryers\Pages;

use App\Filament\Resources\ProduksiPressDryers\ProduksiPressDryerResource;
use App\Filament\Resources\ProduksiPressDryers\Widgets\ProduksiPressDryerSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPressDryer extends ViewRecord
{
    protected static string $resource = ProduksiPressDryerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPressDryerSummaryWidget::class,
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
