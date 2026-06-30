<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Pages;

use App\Filament\Resources\ProduksiGrajiBalkens\ProduksiGrajiBalkenResource;
use App\Filament\Resources\ProduksiGrajiBalkens\Widgets\ProduksiGrajiBalkenSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiGrajiBalken extends ViewRecord
{
    protected static string $resource = ProduksiGrajiBalkenResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiGrajiBalkenSummaryWidget::class,
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
