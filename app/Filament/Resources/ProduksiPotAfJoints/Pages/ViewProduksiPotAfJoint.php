<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\Pages;

use App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource as ProduksiPotAfJointsProduksiPotAfJointResource;
use App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource;
use App\Filament\Resources\ProduksiPotAfJoints\Widgets\ProduksiPotAfJointSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPotAfJoint extends ViewRecord
{
    protected static string $resource = ProduksiPotAfJointResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiPotAfJointSummaryWidget::class,
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
