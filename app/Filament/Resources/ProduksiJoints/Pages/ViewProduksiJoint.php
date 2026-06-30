<?php

namespace App\Filament\Resources\ProduksiJoints\Pages;

use App\Filament\Resources\ProduksiJoints\ProduksiJointResource;
use App\Filament\Resources\ProduksiJoints\Widgets\ProduksiJointSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiJoint extends ViewRecord
{
    protected static string $resource = ProduksiJointResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiJointSummaryWidget::class,
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
