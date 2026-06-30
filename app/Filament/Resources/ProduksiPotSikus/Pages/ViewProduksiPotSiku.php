<?php

namespace App\Filament\Resources\ProduksiPotSikus\Pages;

use App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource as ProduksiPotAfJointsProduksiPotAfJointResource;
use App\Filament\Resources\ProduksiPotSikus\ProduksiPotSikuResource;
use App\Filament\Resources\ProduksiPotSikus\Widgets\ProduksiPotSikuSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPotSiku extends ViewRecord
{
    protected static string $resource = ProduksiPotSikuResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPotSikuSummaryWidget::class,
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
