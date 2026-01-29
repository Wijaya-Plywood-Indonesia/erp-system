<?php

namespace App\Filament\Resources\ProduksiGuellotines\Pages;

use App\Filament\Resources\ProduksiGuellotines\ProduksiGuellotineResource;
use App\Filament\Resources\ProduksiGuellotines\Widgets\ProduksiGuellotineWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiGuellotine extends ViewRecord
{
    protected static string $resource = ProduksiGuellotineResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            // Tambahkan widget yang diperlukan di sini
            ProduksiGuellotineWidget::class,
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
