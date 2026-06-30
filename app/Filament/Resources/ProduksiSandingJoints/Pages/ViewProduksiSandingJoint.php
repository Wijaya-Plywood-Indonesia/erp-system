<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Pages;

use App\Filament\Resources\ProduksiSandingJoints\ProduksiSandingJointResource;
use App\Filament\Resources\ProduksiSandingJoints\Widgets\ProduksiSandingJointSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiSandingJoint extends ViewRecord
{
    protected static string $resource = ProduksiSandingJointResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiSandingJointSummaryWidget::class,
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
