<?php

namespace App\Filament\Resources\ProduksiStiks\Pages;

use App\Filament\Resources\ProduksiStiks\ProduksiStikResource;
use App\Filament\Resources\ProduksiStiks\Widgets\ProduksiStikSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiStik extends ViewRecord
{
    protected static string $resource = ProduksiStikResource::class;
    // 🔥 WAJIB: tampilkan relation manager di halaman View
    protected static bool $showRelationManagers = true;

    // 🔥 WAJIB: izinkan tombol-tombol header tampil (termasuk CREATE)
    protected static bool $canViewAny = true;
    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiStikSummaryWidget::class,
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
