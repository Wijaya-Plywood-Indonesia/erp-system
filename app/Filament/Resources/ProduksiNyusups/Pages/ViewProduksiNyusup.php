<?php

namespace App\Filament\Resources\ProduksiNyusups\Pages;

use App\Filament\Resources\ProduksiNyusups\ProduksiNyusupResource;
use App\Filament\Resources\ProduksiNyusups\Widgets\ProduksiNyusupSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiNyusup extends ViewRecord
{
    protected static string $resource = ProduksiNyusupResource::class;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiNyusupSummaryWidget::class,
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
